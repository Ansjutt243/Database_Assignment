"""
Browser test for NFR-09 (works in current Chrome / Edge / Firefox).

Drives a real browser (headless) through the main flows of the system,
checks the JavaScript auto-fill, accepts the confirmation dialogs, records
JavaScript errors and saves a screenshot of every screen.

  Chrome / Edge (Chromium): Chrome DevTools Protocol
  Firefox:                  WebDriver BiDi

Usage (needs the system running: ./start.sh, optionally Apache):
  python3 tests/browser_test.py chrome  [base-url]
  python3 tests/browser_test.py firefox [base-url]
Requires: pip install websocket-client
Screenshots go to docs/screenshots/<browser>/; results to tests/browser_results_<browser>.md
"""
import base64, json, os, shutil, subprocess, sys, tempfile, time, urllib.request
from pathlib import Path

import websocket  # websocket-client

ROOT = Path(__file__).resolve().parent.parent
BROWSERS = {
    "chrome":  "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
    "firefox": "/Applications/Firefox.app/Contents/MacOS/firefox",
}
PASSWORD = "Helpdesk2026"


# --------------------------------------------------------------------- drivers
class Chrome:
    """Minimal Chrome DevTools Protocol client."""
    port = 9333

    def __init__(self, exe, profile):
        self.proc = subprocess.Popen([exe, "--headless=new", f"--remote-debugging-port={self.port}",
                                      "--remote-allow-origins=*", f"--user-data-dir={profile}",
                                      "--ignore-certificate-errors", "--window-size=1366,900",
                                      "--hide-scrollbars", "--no-first-run", "about:blank"],
                                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        url = None
        for _ in range(50):
            try:
                pages = json.load(urllib.request.urlopen(f"http://127.0.0.1:{self.port}/json/list"))
                url = next(p["webSocketDebuggerUrl"] for p in pages if p["type"] == "page")
                break
            except Exception:
                time.sleep(0.2)
        self.ws = websocket.create_connection(url, timeout=30)
        self.n = 0
        self.errors, self.dialogs, self.loaded = [], [], False
        for m in ("Page.enable", "Runtime.enable", "Log.enable"):
            self.call(m)
        self.version = self.call("Runtime.evaluate", {"expression": "navigator.userAgent",
                                                      "returnByValue": True})["result"]["value"]

    def _event(self, msg):
        method = msg.get("method")
        if method == "Page.loadEventFired":
            self.loaded = True
        elif method == "Page.javascriptDialogOpening":
            self.dialogs.append(msg["params"]["message"])
            self.call("Page.handleJavaScriptDialog", {"accept": True})
        elif method == "Runtime.exceptionThrown":
            self.errors.append(msg["params"]["exceptionDetails"].get("text", "exception"))
        elif method == "Log.entryAdded" and msg["params"]["entry"]["level"] == "error":
            e = msg["params"]["entry"]
            if "favicon" not in e.get("url", "") and "403" not in e.get("text", ""):
                self.errors.append(e["text"])

    def call(self, method, params=None):
        self.n += 1
        my = self.n
        self.ws.send(json.dumps({"id": my, "method": method, "params": params or {}}))
        while True:
            msg = json.loads(self.ws.recv())
            if msg.get("id") == my:
                if "error" in msg:
                    raise RuntimeError(f"{method}: {msg['error']}")
                return msg.get("result", {})
            self._event(msg)

    def wait_load(self, timeout=15):
        end = time.time() + timeout
        while not self.loaded and time.time() < end:
            self.ws.settimeout(max(0.1, end - time.time()))
            try:
                self._event(json.loads(self.ws.recv()))
            except websocket.WebSocketTimeoutException:
                break
        self.ws.settimeout(30)
        time.sleep(0.3)

    def goto(self, url):
        self.loaded = False
        self.call("Page.navigate", {"url": url})
        self.wait_load()

    def js(self, expr):
        r = self.call("Runtime.evaluate", {"expression": expr, "returnByValue": True, "awaitPromise": True})
        if "exceptionDetails" in r:
            raise RuntimeError(r["exceptionDetails"].get("text"))
        return r["result"].get("value")

    def submit(self, expr):
        """Run JS that submits a form, then wait for the next page."""
        self.loaded = False
        self.js(expr)
        self.wait_load()

    def screenshot(self, path):
        m = self.call("Page.getLayoutMetrics")["cssContentSize"]
        clip = {"x": 0, "y": 0, "width": m["width"], "height": min(m["height"], 4000), "scale": 1}
        data = self.call("Page.captureScreenshot", {"format": "png", "clip": clip, "captureBeyondViewport": True})["data"]
        path.write_bytes(base64.b64decode(data))

    def close(self):
        try:
            self.call("Browser.close")
        except Exception:
            pass
        self.proc.terminate()


class Firefox:
    """Minimal WebDriver BiDi client."""
    port = 9334

    def __init__(self, exe, profile):
        self.proc = subprocess.Popen([exe, "--headless", "--no-remote", "--profile", profile,
                                      f"--remote-debugging-port={self.port}", "--width=1366", "--height=900"],
                                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(100):
            try:
                self.ws = websocket.create_connection(f"ws://127.0.0.1:{self.port}/session", timeout=30)
                break
            except Exception:
                time.sleep(0.2)
        self.n = 0
        self.errors, self.dialogs, self.loaded = [], [], False
        caps = self.call("session.new", {"capabilities": {"alwaysMatch": {"acceptInsecureCerts": True,
                                                                         "unhandledPromptBehavior": {"default": "ignore"}}}})
        self.version = "Firefox " + caps["capabilities"]["browserVersion"]
        self.call("session.subscribe", {"events": ["browsingContext.load", "browsingContext.userPromptOpened", "log.entryAdded"]})
        self.ctx = self.call("browsingContext.getTree", {})["contexts"][0]["context"]

    def _event(self, msg):
        method = msg.get("method")
        if method == "browsingContext.load":
            self.loaded = True
        elif method == "browsingContext.userPromptOpened":
            self.dialogs.append(msg["params"].get("message", ""))
            self.n += 1
            self.ws.send(json.dumps({"id": self.n, "method": "browsingContext.handleUserPrompt",
                                     "params": {"context": msg["params"]["context"], "accept": True}}))
        elif method == "log.entryAdded" and msg["params"].get("level") == "error":
            text = msg["params"].get("text", "")
            if "403" not in text and "favicon" not in text:
                self.errors.append(text)

    def call(self, method, params):
        self.n += 1
        my = self.n
        self.ws.send(json.dumps({"id": my, "method": method, "params": params}))
        while True:
            msg = json.loads(self.ws.recv())
            if msg.get("id") == my:
                if msg.get("type") == "error":
                    raise RuntimeError(f"{method}: {msg.get('message')}")
                return msg.get("result", {})
            if "method" in msg:
                self._event(msg)

    def wait_load(self, timeout=15):
        end = time.time() + timeout
        while not self.loaded and time.time() < end:
            self.ws.settimeout(max(0.1, end - time.time()))
            try:
                msg = json.loads(self.ws.recv())
                if "method" in msg:
                    self._event(msg)
            except websocket.WebSocketTimeoutException:
                break
        self.ws.settimeout(30)
        time.sleep(0.3)

    def goto(self, url):
        self.call("browsingContext.navigate", {"context": self.ctx, "url": url, "wait": "complete"})
        time.sleep(0.3)

    def js(self, expr):
        r = self.call("script.evaluate", {"expression": expr, "target": {"context": self.ctx},
                                          "awaitPromise": True, "resultOwnership": "none"})
        if r.get("type") == "exception":
            raise RuntimeError(r["exceptionDetails"].get("text"))
        v = r["result"]
        return v.get("value")

    def submit(self, expr):
        self.loaded = False
        self.js(expr)
        self.wait_load()

    def screenshot(self, path):
        data = self.call("browsingContext.captureScreenshot", {"context": self.ctx, "origin": "document"})["data"]
        path.write_bytes(base64.b64decode(data))

    def close(self):
        try:
            self.call("browser.close", {})
        except Exception:
            pass
        self.proc.terminate()


# --------------------------------------------------------------------- scenario
def main():
    name = sys.argv[1] if len(sys.argv) > 1 else "chrome"
    base = (sys.argv[2] if len(sys.argv) > 2 else "http://127.0.0.1:8080").rstrip("/")
    shots = ROOT / "docs" / "screenshots" / name
    shutil.rmtree(shots, ignore_errors=True)
    shots.mkdir(parents=True)
    profile = tempfile.mkdtemp(prefix=f"mlh-{name}-")
    b = (Chrome if name == "chrome" else Firefox)(BROWSERS[name], profile)
    results = []

    def check(test, ok, detail=""):
        results.append((test, bool(ok), detail))
        print(f"{'PASS' if ok else 'FAIL'}  {test}  {detail}")

    def shot(file):
        b.screenshot(shots / f"{file}.png")

    def text(sel):
        return b.js(f"(document.querySelector({json.dumps(sel)}) || {{}}).textContent || ''")

    def value(sel):
        return b.js(f"(document.querySelector({json.dumps(sel)}) || {{}}).value || ''")

    def login(user, pw=PASSWORD):
        b.goto(base + "/logout.php")
        b.goto(base + "/index.php")
        b.submit(f"document.getElementById('username').value={json.dumps(user)};"
                 f"document.getElementById('password').value={json.dumps(pw)};"
                 "document.querySelector('form').submit()")

    try:
        # --- F1 log in
        b.goto(base + "/index.php")
        check("Login page renders", "Sign in" in text("h2"))
        shot("01_login")
        login("a.khan", "wrong-password")
        check("Wrong password shows generic error", "Incorrect username or password" in text(".login"))
        shot("02_login_error")
        b.goto(base + "/index.php")
        b.submit("document.getElementById('username').value='a.khan';"
                 f"document.getElementById('password').value='{PASSWORD}';document.querySelector('form').submit()")
        check("Operator lands on Main Control Page", "Main Control Page" in text(".brand"))
        check("Reports locked for operator", "Management Reports (locked)" in text("main"))
        shot("03_main_operator")

        # --- F2 log new call: JavaScript auto-fill (UR-02, UR-03, UR-04)
        b.goto(base + "/calls/new.php")
        b.js("var c=document.getElementById('CallerID'); c.value='E0045'; c.dispatchEvent(new Event('change'));"
             "var s=document.getElementById('SerialNumber'); s.value='HP-5CG1234XYZ'; s.dispatchEvent(new Event('change'));")
        time.sleep(1.5)
        check("Caller auto-fill (job title)", value("#c_job") == "Property Manager", value("#c_job"))
        check("Caller auto-fill (office)", value("#c_office") == "Lahore", value("#c_office"))
        check("Equipment auto-fill (type/make)", "Laptop" in value("#h_type"), value("#h_type"))
        check("Warranty badge", text("#warranty_badge") == "Under warranty", text("#warranty_badge"))
        check("Installed software with licence status", "Valid" in value("#h_installed"), value("#h_installed")[:60])
        check("OS pre-selected from installed software", value("#OSSoftwareID") != "", value("#OSSoftwareID"))
        expected = text(".problem-no")
        b.js("document.getElementById('ProblemTypeID').value='11';document.getElementById('Priority').value='High';"
             "document.getElementById('UrgencyLevel').value='Distressed';"
             "document.getElementById('CallReason').value='Excel crashes when opening shared workbook';"
             "document.getElementById('Description').value='Started after update last night. Error code 0xc0000005. User has saved copy.';"
             "document.getElementById('SoftwareID').value='3';")
        shot("04_log_new_call")
        b.submit("document.querySelector('form[data-confirm]').requestSubmit()")
        check("Confirmation dialog shown before saving", any("Save this call" in d for d in b.dialogs))
        check("Saved problem number = number shown before saving", expected in text(".flash-success"), expected)
        check("Assign Technician opens after saving", "Assign Technician" in text(".brand"))
        check("Least busy skilled technician recommended", "recommended" in text("tr.recommended"),
              " ".join(text("tr.recommended").split())[:60])
        shot("05_assign_technician")
        # the operator may override the recommendation: choose Usman Tariq (ID 3)
        b.submit("document.querySelector('input[name=TechnicianID][value=\"3\"]').checked = true;"
                 "document.querySelector('form').requestSubmit()")
        check("Technician assigned", "Technician assigned" in text(".flash-success"))
        check("Operator can override recommendation", "Usman Tariq" in b.js("document.body.innerText.split('Call history')[0]"))
        shot("06_problem_details")
        problem = expected

        # --- F4 follow-up
        b.goto(base + "/calls/followup.php?problem=" + problem)
        b.js("document.getElementById('UrgencyLevel').value='Distressed';"
             "document.getElementById('CallReason').value='Chasing progress - still cannot open file';")
        shot("07_follow_up_call")
        b.submit("document.querySelectorAll('form')[1].requestSubmit()")
        check("Follow-up recorded", "Follow-up call recorded" in text(".flash-success"))

        # --- client-side validation (layer 1)
        b.goto(base + "/calls/new.php")
        valid = b.js("document.querySelector('form[data-confirm]').checkValidity()")
        check("Browser blocks empty required fields", valid is False)

        # --- access control as operator
        b.goto(base + "/reports/index.php")
        check("Operator gets Access denied on reports", "Access denied" in text("h2"))
        shot("08_access_denied_operator")
        b.goto(base + "/maintenance/index.php")
        shot("09_maintenance_operator")
        b.goto(base + "/calls/open.php")
        shot("10_open_problems")

        # --- F5 resolve as technician (own job)
        login("u.tariq")
        b.goto(base + "/calls/resolve.php")
        shot("11_resolve_my_jobs")
        check("Technician sees the new job in My open jobs", problem in text("main"))
        b.goto(base + "/calls/resolve.php?problem=" + problem)
        b.js("document.getElementById('TimeTakenHours').value='3.5';"
             "document.getElementById('ResolutionNotes').value='Repaired Office 365 installation and cleared cached credentials; file opens.';")
        shot("12_resolve_problem")
        b.submit("document.querySelector('form[data-confirm]').requestSubmit()")
        check("Technician resolves own job", "resolved and closed" in text(".flash-success"))

        # --- F6 reports as manager
        login("i.shah")
        check("Manager main page hides Log New Call", "Log New Call" not in text("main"))
        shot("13_main_manager")
        b.goto(base + "/calls/new.php")
        check("Manager gets Access denied on Log New Call", "Access denied" in text("h2"))
        b.goto(base + "/reports/index.php")
        shot("14_reports_menu")
        for code, q, label in [("R1", "month=2026-10", "15_report_R1"), ("R2", "month=2026-10", "16_report_R2"),
                               ("R3", "month=2026-10", "17_report_R3"), ("R4", "", "18_report_R4"),
                               ("R5", "month=2026-10&technician=3", "19_report_R5"),
                               ("R6", "month=2026-10&office=1", "20_report_R6")]:
            b.goto(f"{base}/reports/view.php?r={code}&{q}")
            check(f"Report {code} renders", code in text("h2") and b.js("document.querySelectorAll('table.data').length") > 0)
            shot(label)

        # --- F7 backup from the main page (every user)
        b.goto(base + "/main.php")
        b.submit("document.querySelector('form[action$=\"maintenance/index.php\"]').requestSubmit()")
        check("Manager can back up from Main Control Page", "Backup saved" in text(".flash-success"))

        # --- F10 admin
        login("w.anwar")
        shot("21_main_admin")
        b.goto(base + "/registers/hardware.php?edit=1")
        shot("22_hardware_register")
        b.goto(base + "/registers/employees.php")
        shot("23_employees_register")
        b.goto(base + "/registers/technicians.php")
        shot("24_technicians_skills")
        b.goto(base + "/registers/software.php")
        shot("25_software_licences")
        b.goto(base + "/users/index.php")
        shot("26_user_accounts")
        b.goto(base + "/maintenance/index.php")
        b.submit("document.querySelector('button[value=\"check\"]').form.requestSubmit(document.querySelector('button[value=\"check\"]'))")
        check("Admin integrity check passes", "Integrity check passed" in text(".flash-success"))
        shot("27_integrity_check")

        check("No JavaScript errors on any page", not b.errors, "; ".join(b.errors)[:120])
    finally:
        version = getattr(b, "version", name)
        b.close()
        shutil.rmtree(profile, ignore_errors=True)

    passed = sum(1 for _, ok, _ in results if ok)
    md = [f"# Browser test results – {name}", "",
          f"Browser: `{version}`  ", f"URL: `{base}`  ", f"Run: {time.strftime('%d/%m/%Y %H:%M')}", "",
          "| # | Test | Result | Detail |", "|---|---|---|---|"]
    md += [f"| {i} | {t} | {'PASS' if ok else 'FAIL'} | {d.replace('|', '/')} |" for i, (t, ok, d) in enumerate(results, 1)]
    md += ["", f"**{passed} of {len(results)} passed.** Screenshots: `docs/screenshots/{name}/`"]
    (ROOT / "tests" / f"browser_results_{name}.md").write_text("\n".join(md) + "\n")
    print(f"\n{passed}/{len(results)} passed")
    sys.exit(0 if passed == len(results) else 1)


if __name__ == "__main__":
    main()
