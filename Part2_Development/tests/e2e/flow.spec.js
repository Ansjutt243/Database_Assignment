// =====================================================================
// ML IT Helpdesk - A-to-Z flow test with video (Playwright)
//
// Seven flows, run in order. Each flow is recorded as its own video and
// every step is shown as a caption at the top of the screen.
//   01 Log-in & account lock-out        05 Manager: reports R1-R6
//   02 Admin: registers & users CRUD    06 Maintenance: backup/restore/compact/check
//   03 Operator: call flow              07 Access control (role permissions)
//   04 Technician: resolve own job
//
// Run:  ./tests/e2e/run_e2e.sh   (see docs/08_E2E_Flow_Test.md)
// The test adds and deletes its own records (names start with "Test"/"TEST").
// =====================================================================
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const PASSWORD = 'Helpdesk2026';
const SHOTS = path.resolve(__dirname, '../../docs/screenshots/e2e');
const STEPS = path.resolve(__dirname, 'results/steps.json');
const today = new Date().toISOString().slice(0, 10);

fs.mkdirSync(SHOTS, { recursive: true });
fs.mkdirSync(path.dirname(STEPS), { recursive: true });
const steps = [];                         // [{flow, step, result}] for the document

test.describe.configure({ mode: 'serial' });

// ------------------------------------------------------------------ helpers
// Caption bar: kept in sessionStorage so it survives page loads.
const CAPTION = `
  (() => {
    const show = () => {
      const t = sessionStorage.getItem('e2e_caption');
      if (!t || !document.body) return;
      let d = document.getElementById('e2e-caption');
      if (!d) {
        d = document.createElement('div');
        d.id = 'e2e-caption';
        d.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:99999;padding:10px 18px;' +
          'background:rgba(17,24,39,.92);color:#fff;font:600 17px/1.35 system-ui,sans-serif;' +
          'border-top:4px solid #22c55e;pointer-events:none';
        document.body.appendChild(d);
      }
      d.textContent = t;
    };
    window.__e2eCaption = show;
    document.addEventListener('DOMContentLoaded', show);
  })();`;

let flowName = '';
let shotNo = 0;

async function caption(page, text, pause = 900) {
  await page.evaluate(t => { sessionStorage.setItem('e2e_caption', t); window.__e2eCaption && window.__e2eCaption(); }, text);
  await page.waitForTimeout(pause);
}

/** One checked step: caption, action, assertion, screenshot, record result. */
async function step(page, text, fn) {
  await caption(page, text);
  try {
    await fn();
    await caption(page, '✔ ' + text, 700);
    steps.push({ flow: flowName, step: text, result: 'Pass' });
  } catch (e) {
    steps.push({ flow: flowName, step: text, result: 'FAIL: ' + String(e.message).split('\n')[0] });
    throw e;
  } finally {
    shotNo += 1;
    await page.screenshot({ path: path.join(SHOTS, `${String(shotNo).padStart(2, '0')}_${slug(text)}.png`) }).catch(() => {});
  }
}

const slug = s => s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 50);

async function login(page, user, pw = PASSWORD) {
  await page.goto('/logout.php');
  await page.goto('/index.php');
  await page.fill('#username', user);
  await page.fill('#password', pw);
  await page.click('button:has-text("Log in")');
}

const flash = page => page.locator('.flash-success');
const flashError = page => page.locator('.flash-error');
const row = (page, text) => page.locator('table.data tr', { hasText: text });

test.beforeEach(async ({ page }, info) => {
  flowName = info.title;
  await page.addInitScript(CAPTION);
  page.on('dialog', d => d.accept());           // "Are you sure?" confirmations
});

test.afterAll(() => fs.writeFileSync(STEPS, JSON.stringify(steps, null, 2)));

// ===================================================================== 01
test('01 Log-in and account lock-out', async ({ page }) => {
  await page.goto('/index.php');
  await caption(page, 'Flow 1: Log-in, wrong password and account lock-out (NFR-02)', 1500);

  await step(page, 'Wrong password shows a general error (does not say which part is wrong)', async () => {
    await page.fill('#username', 'a.khan');
    await page.fill('#password', 'wrong-pass');
    await page.click('button:has-text("Log in")');
    await expect(page.locator('.login')).toContainText('Incorrect username or password');
  });

  await step(page, 'Five wrong passwords lock the account of f.mehmood', async () => {
    for (let i = 1; i <= 5; i++) {
      await page.fill('#username', 'f.mehmood');
      await page.fill('#password', 'wrong-' + i);
      await page.click('button:has-text("Log in")');
    }
    await expect(page.locator('.login')).toContainText('This account is now locked');
  });

  await step(page, 'Even the correct password is refused while locked', async () => {
    await login(page, 'f.mehmood');
    await expect(page.locator('.login')).toContainText('This account is locked');
  });

  await step(page, 'Administrator w.anwar unlocks the account in User Accounts', async () => {
    await login(page, 'w.anwar');
    await page.goto('/users/index.php');
    await expect(row(page, 'f.mehmood')).toContainText('Locked');
    await row(page, 'f.mehmood').locator('button:has-text("Unlock")').click();
    await expect(flash(page)).toContainText('Account unlocked');
  });

  await step(page, 'f.mehmood can log in again and reaches the Main Control Page', async () => {
    await login(page, 'f.mehmood');
    await expect(page.locator('.brand')).toContainText('Main Control Page');
  });

  await step(page, 'Log out ends the session', async () => {
    await page.goto('/logout.php');
    await expect(page.locator('h2')).toContainText('Sign in');
  });
});

// ===================================================================== 02
test('02 Admin - registers and users (add, edit, delete)', async ({ page }) => {
  await login(page, 'w.anwar');
  await caption(page, 'Flow 2: System Administrator (w.anwar) adds, edits and deletes register entries', 1500);

  // ---------------- Offices
  await step(page, 'Office: add "Test Office Murree"', async () => {
    await page.goto('/registers/offices.php');
    await page.click('a:has-text("Add office")');
    await page.fill('#f_OfficeName', 'Test Office Murree');
    await page.selectOption('#f_Specialisation', { index: 1 });
    await page.fill('#f_ContactPhone', '051-1234567');
    await page.fill('#f_AddressLine', '12 Mall Road');
    await page.fill('#f_City', 'Murree');
    await page.fill('#f_Postcode', '47150');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Office saved');
    await expect(row(page, 'Test Office Murree')).toBeVisible();
  });

  await step(page, 'Office: server-side check rejects an invalid phone number', async () => {
    await row(page, 'Test Office Murree').locator('a:has-text("Edit")').click();
    // remove the browser check (layer 1) to show the PHP check (layer 2)
    await page.evaluate(() => document.getElementById('f_ContactPhone').removeAttribute('pattern'));
    await page.fill('#f_ContactPhone', 'abc');
    await page.click('button:has-text("Save")');
    await expect(page.locator('.has-error')).toContainText('valid phone number');
  });

  await step(page, 'Office: edit the phone number and save', async () => {
    await page.fill('#f_ContactPhone', '051-7654321');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Office saved');
    await expect(row(page, 'Test Office Murree')).toContainText('051-7654321');
  });

  await step(page, 'Office: delete "Test Office Murree" (after "Are you sure?")', async () => {
    await row(page, 'Test Office Murree').locator('button:has-text("Delete")').click();
    await expect(flash(page)).toContainText('Office deleted');
    await expect(row(page, 'Test Office Murree')).toHaveCount(0);
  });

  await step(page, 'Office in use (Lahore) cannot be deleted - clear message', async () => {
    await row(page, 'Lahore').first().locator('button:has-text("Delete")').click();
    await expect(flashError(page)).toContainText('cannot be deleted');
  });

  // ---------------- Hardware
  await step(page, 'Hardware: add laptop TEST-HW-0001', async () => {
    await page.goto('/registers/hardware.php');
    await page.click('a:has-text("Add hardware")');
    await page.fill('#f_SerialNumber', 'TEST-HW-0001');
    await page.selectOption('#f_EquipmentTypeID', '2');
    await page.selectOption('#f_ManufacturerID', '1');
    await page.fill('#f_Make', 'EliteBook');
    await page.fill('#f_Model', '840 G10');
    await page.fill('#f_PurchaseDate', '2026-01-15');
    await page.fill('#f_WarrantyExpiry', '2029-01-15');
    await page.selectOption('#f_OfficeID', '1');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('TEST-HW-0001 saved');
  });

  await step(page, 'Hardware: edit the model', async () => {
    await page.fill('#f_Model', '840 G11');
    await page.click('button.btn-primary:has-text("Save")');
    await expect(flash(page)).toContainText('saved');
    await expect(page.locator('#f_Model')).toHaveValue('840 G11');
  });

  await step(page, 'Hardware: install licensed software on the laptop', async () => {
    await page.selectOption('#LicenceID', { index: 1 });
    await page.click('button[value="install"]');
    await expect(flash(page)).toContainText('Installed software updated');
    await expect(page.locator('section.card', { hasText: 'Installed software' }).locator('table.data tr')).toHaveCount(2);
  });

  await step(page, 'Hardware: remove the installed software', async () => {
    await page.click('button[value="uninstall"]');
    await expect(flash(page)).toContainText('Installed software updated');
    await expect(page.locator('section.card', { hasText: 'Installed software' }).locator('table.data tr')).toHaveCount(1);
  });

  await step(page, 'Hardware: delete TEST-HW-0001', async () => {
    await page.click('button[value="delete"]');
    await expect(flash(page)).toContainText('Hardware item deleted');
  });

  // ---------------- Software & licences
  await step(page, 'Software: add "Test Analytics Suite 1.0"', async () => {
    await page.goto('/registers/software.php');
    await page.click('a:has-text("Add software")');
    await page.fill('#f_SoftwareName', 'Test Analytics Suite');
    await page.fill('#f_Version', '1.0');
    await page.selectOption('#f_SoftwareCategory', { index: 1 });
    await page.selectOption('#f_ManufacturerID', { index: 1 });
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Software saved');
  });

  await step(page, 'Software: edit the version to 1.1', async () => {
    await row(page, 'Test Analytics Suite').locator('a:has-text("Edit")').click();
    await page.fill('#f_Version', '1.1');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Software saved');
    await expect(row(page, 'Test Analytics Suite')).toContainText('1.1');
  });

  await step(page, 'Licence: add LIC-TEST-0001 (10 seats) for the new software', async () => {
    await page.click('a:has-text("Add licence")');
    await page.fill('#f_LicenceNumber', 'LIC-TEST-0001');
    const value = await page.locator('#f_SoftwareID option', { hasText: 'Test Analytics Suite' }).getAttribute('value');
    await page.selectOption('#f_SoftwareID', value);
    await page.selectOption('#f_LicenceType', { index: 1 });
    await page.fill('#f_Seats', '10');
    await page.fill('#f_StartDate', '2026-01-01');
    await page.fill('#f_ExpiryDate', '2027-12-31');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Licence saved');
  });

  await step(page, 'Licence: edit seats to 20', async () => {
    await row(page, 'LIC-TEST-0001').locator('a:has-text("Edit")').click();
    await page.fill('#f_Seats', '20');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Licence saved');
    await expect(row(page, 'LIC-TEST-0001')).toContainText('/ 20');
  });

  // ---------------- Employees
  await step(page, 'Employee: add E0901 Sara Test', async () => {
    await page.goto('/registers/employees.php');
    await page.click('a:has-text("Add employee")');
    await page.fill('#f_EmployeeID', 'E0901');
    await page.fill('#f_FirstName', 'Sara');
    await page.fill('#f_LastName', 'Test');
    await page.fill('#f_JobTitle', 'Data Analyst');
    await page.fill('#f_ContactPhone', '0300-1234567');
    await page.fill('#f_Email', 'sara.test@manzaneque.pk');
    await page.selectOption('#f_OfficeID', '1');
    await page.selectOption('#f_DepartmentID', { index: 1 });
    await page.fill('#f_StartDate', '2026-01-05');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Employee E0901 saved');
  });

  await step(page, 'Employee: edit the job title', async () => {
    await page.goto('/registers/employees.php?edit=E0901');
    await page.fill('#f_JobTitle', 'Senior Data Analyst');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Employee E0901 saved');
  });

  // ---------------- Technicians
  await step(page, 'Technician: make Sara Test a technician with two skills', async () => {
    await page.goto('/registers/technicians.php');
    await page.click('a:has-text("Add technician")');
    const value = await page.locator('#EmployeeID option', { hasText: 'Sara Test' }).getAttribute('value');
    await page.selectOption('#EmployeeID', value);
    await page.locator('input[name="skills[]"]').nth(0).check();
    await page.locator('input[name="skills[]"]').nth(1).check();
    await page.click('button[value="add"]');
    await expect(flash(page)).toContainText('Technician saved');
  });

  await step(page, 'Technician: edit - mark as on leave and add a third skill', async () => {
    await row(page, 'Sara Test').locator('a:has-text("Edit")').click();
    await page.locator('input[name="IsAvailable"]').uncheck();
    await page.locator('input[name="skills[]"]').nth(2).check();
    await page.click('button[value="save"]');
    await expect(flash(page)).toContainText('Technician saved');
  });

  // ---------------- User accounts
  await step(page, 'User: passwords that do not match are rejected', async () => {
    await page.goto('/users/index.php');
    await page.click('a:has-text("Create account")');
    await page.fill('#f_Username', 's.test');
    const value = await page.locator('#f_EmployeeID option', { hasText: 'Sara Test' }).getAttribute('value');
    await page.selectOption('#f_EmployeeID', value);
    await page.selectOption('#f_Role', 'Technician');
    await page.fill('#f_Password', 'Welcome2026');
    await page.fill('#f_Password2', 'Welcome2027');
    await page.click('button:has-text("Save")');
    await expect(page.locator('.has-error')).toContainText('do not match');
  });

  await step(page, 'User: create account s.test (Technician)', async () => {
    await page.fill('#f_Password', 'Welcome2026');
    await page.fill('#f_Password2', 'Welcome2026');
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Account saved');
    await expect(row(page, 's.test')).toContainText('IT Technician');
  });

  await step(page, 'User: change the role of s.test to Helpdesk Operator', async () => {
    await row(page, 's.test').locator('a:has-text("Edit")').click();
    await page.selectOption('#f_Role', 'Operator');
    await page.click('button:has-text("Save")');
    await expect(row(page, 's.test')).toContainText('Helpdesk Operator');
  });

  await step(page, 'User: disable and then unlock s.test', async () => {
    await row(page, 's.test').locator('button:has-text("Disable")').click();
    await expect(flash(page)).toContainText('Account disabled');
    await expect(row(page, 's.test')).toContainText('Locked');
    await row(page, 's.test').locator('button:has-text("Unlock")').click();
    await expect(flash(page)).toContainText('Account unlocked');
  });

  await step(page, 'New user s.test logs in with the password set by the admin', async () => {
    await login(page, 's.test', 'Welcome2026');
    await expect(page.locator('.brand')).toContainText('Main Control Page');
    await login(page, 'w.anwar');
  });

  // ---------------- Ex-employee: never deleted, end date instead
  await step(page, 'Employee leaves: enter an End Date (record is kept, not deleted)', async () => {
    await page.goto('/registers/employees.php?edit=E0901');
    await page.fill('#f_EndDate', today);
    await page.click('button:has-text("Save")');
    await expect(flash(page)).toContainText('Employee E0901 saved');
  });
});

// ===================================================================== 03
let problem = '';

test('03 Operator - log, assign, follow-up and search a call', async ({ page }) => {
  await login(page, 'a.khan');
  await caption(page, 'Flow 3: Helpdesk Operator (a.khan) handles a new call', 1500);

  await step(page, 'Main Control Page: operator buttons, reports locked', async () => {
    await expect(page.locator('.brand')).toContainText('Main Control Page');
    await expect(page.locator('main')).toContainText('Management Reports (locked)');
  });

  await step(page, 'Log New Call: choosing the caller fills job title and office', async () => {
    await page.click('a:has-text("Log New Call")');
    await page.selectOption('#CallerID', 'E0045');
    await expect(page.locator('#c_office')).toHaveValue('Lahore');
    await expect(page.locator('#c_job')).not.toHaveValue('');
  });

  await step(page, 'Typing the serial number fills equipment, warranty and installed software', async () => {
    await page.fill('#SerialNumber', 'HP-5CG1234XYZ');
    await page.locator('#SerialNumber').dispatchEvent('change');
    await expect(page.locator('#h_type')).toHaveValue(/Laptop/);
    await expect(page.locator('#warranty_badge')).toHaveText('Under warranty');
  });

  await step(page, 'Problem details entered; the problem number is shown before saving', async () => {
    problem = (await page.locator('.problem-no').innerText()).trim();
    await page.selectOption('#ProblemTypeID', '11');
    await page.selectOption('#Priority', 'High');
    await page.selectOption('#UrgencyLevel', 'Distressed');
    await page.selectOption('#SoftwareID', '3');
    await page.fill('#CallReason', 'Excel crashes when opening shared workbook');
    await page.fill('#Description', 'Started after the update last night. Error code 0xc0000005. User has a saved copy.');
    expect(problem).toMatch(/^P-\d+$/);
  });

  await step(page, 'Save & Assign: same problem number confirmed after saving', async () => {
    await page.click('button:has-text("Save & Assign")');
    await expect(flash(page)).toContainText(problem);
    await expect(page.locator('tr.recommended')).toContainText('recommended');
  });

  await step(page, 'Assign Technician: operator chooses Usman Tariq', async () => {
    await page.check('input[name="TechnicianID"][value="3"]');
    await page.click('button:has-text("Assign Selected Technician")');
    await expect(flash(page)).toContainText('Technician assigned');
  });

  await step(page, 'Follow-up call: the caller rings again about the same problem', async () => {
    await page.goto('/calls/followup.php?problem=' + problem);
    await page.selectOption('#UrgencyLevel', 'Distressed');
    await page.fill('#CallReason', 'Chasing progress - still cannot open the file');
    await page.click('button:has-text("Save Follow-up")');
    await expect(flash(page)).toContainText('Follow-up call recorded');
  });

  await step(page, 'Open Problems List: filter High priority, the new problem is listed', async () => {
    await page.goto('/calls/open.php');
    await page.selectOption('#priority', 'High');
    await page.click('button:has-text("Filter")');
    await expect(page.locator('main')).toContainText(problem);
  });

  await step(page, 'Search Problem: full history with both calls', async () => {
    await page.goto('/calls/view.php');
    await page.fill('#problem', problem);
    await page.press('#problem', 'Enter');
    await expect(page.locator('main')).toContainText('Usman Tariq');
    await expect(page.locator('main')).toContainText('Chasing progress');
  });
});

// ===================================================================== 04
test('04 Technician - resolve own job', async ({ page }) => {
  await login(page, 'u.tariq');
  await caption(page, 'Flow 4: IT Technician (u.tariq) resolves his own job', 1500);

  await step(page, 'My open jobs lists the new problem', async () => {
    await page.click('a:has-text("Resolve Problem")');
    await expect(page.locator('main')).toContainText(problem);
  });

  await step(page, 'Resolve: time taken and resolution notes entered', async () => {
    await row(page, problem).locator('a:has-text("Resolve")').click();
    await page.fill('#TimeTakenHours', '3.5');
    await page.fill('#ResolutionNotes', 'Repaired the Office 365 installation and cleared cached credentials; file opens.');
    await expect(page.locator('#DateTimeResolved')).not.toHaveValue('');
  });

  await step(page, 'Mark as Resolved: the problem is closed', async () => {
    await page.click('button:has-text("Mark as Resolved")');
    await expect(flash(page)).toContainText('resolved and closed');
    await expect(page.locator('main')).toContainText('Resolved');
  });
});

// ===================================================================== 05
test('05 Manager - management reports R1 to R6', async ({ page }) => {
  await login(page, 'i.shah');
  await caption(page, 'Flow 5: Office Manager (i.shah) views the management reports', 1500);

  for (const code of ['R1', 'R2', 'R3', 'R4', 'R5', 'R6']) {
    await step(page, `Report ${code} shown on screen`, async () => {
      await page.goto('/reports/index.php');
      await page.click(`button[name="r"][value="${code}"]`);
      await expect(page.locator('h2')).toContainText(code);
      await expect(page.locator('table.data').first()).toBeVisible();
      await page.mouse.wheel(0, 400);
    });
  }
});

// ===================================================================== 06
test('06 Maintenance - backup, restore, compact and integrity check', async ({ page }) => {
  await login(page, 'a.khan');
  await caption(page, 'Flow 6: Database maintenance (Section 12 of the design)', 1500);
  let file = '';

  await step(page, 'Operator: Back Up Now creates a backup file', async () => {
    await page.goto('/maintenance/index.php');
    await page.click('button[value="backup"]');
    await expect(flash(page)).toContainText('Backup saved');
    file = (await flash(page).innerText()).match(/mlhelpdesk_\d{8}_[\d-]+\.sql/)[0];
  });

  await step(page, 'Restore with the wrong confirmation date is cancelled', async () => {
    await page.selectOption('#BackupFile', file);
    await page.fill('#ConfirmDate', '20000101');
    await page.click('button[value="restore"]');
    await expect(flashError(page)).toContainText('Restore cancelled');
  });

  await step(page, 'Restore with the correct date restores the backup', async () => {
    await page.selectOption('#BackupFile', file);
    await page.fill('#ConfirmDate', file.slice(11, 19));
    await page.click('button[value="restore"]');
    await expect(flash(page)).toContainText('restored');
  });

  await step(page, 'Compact Now optimises the tables', async () => {
    await page.click('button[value="compact"]');
    await expect(flash(page)).toBeVisible();
  });

  await step(page, 'Administrator: Run Integrity Check passes', async () => {
    await login(page, 'w.anwar');
    await page.goto('/maintenance/index.php');
    await page.click('button[value="check"]');
    await expect(flash(page)).toContainText('Integrity check passed');
  });

  await step(page, 'Maintenance log records every action with user and time', async () => {
    await page.locator('table.data').last().scrollIntoViewIfNeeded();
    await expect(page.locator('main')).toContainText('Restore');
    await expect(page.locator('main')).toContainText('Compact');
  });
});

// ===================================================================== 07
test('07 Access control - each role only sees its own functions', async ({ page }) => {
  await caption(page, 'Flow 7: Access control (Table 40 access matrix)', 300);

  await step(page, 'Operator cannot open Management Reports', async () => {
    await login(page, 'a.khan');
    await page.goto('/reports/index.php');
    await expect(page.locator('h2')).toContainText('Access denied');
  });

  await step(page, 'Operator cannot open User Accounts', async () => {
    await page.goto('/users/index.php');
    await expect(page.locator('h2')).toContainText('Access denied');
  });

  await step(page, 'Operator sees registers as view only (no Add button)', async () => {
    await page.goto('/registers/offices.php');
    await expect(page.locator('a:has-text("Add office")')).toHaveCount(0);
  });

  await step(page, 'Technician cannot log a new call', async () => {
    await login(page, 'u.tariq');
    await page.goto('/calls/new.php');
    await expect(page.locator('h2')).toContainText('Access denied');
  });

  await step(page, 'Manager cannot assign technicians', async () => {
    await login(page, 'i.shah');
    await page.goto('/calls/assign.php');
    await expect(page.locator('h2')).toContainText('Access denied');
  });

  await step(page, 'Opening a page without logging in goes to the Sign in page', async () => {
    await page.goto('/logout.php');
    await page.goto('/registers/hardware.php');
    await expect(page.locator('h2')).toContainText('Sign in');
  });
});
