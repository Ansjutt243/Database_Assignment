"""Generates 03_pilot_data.sql for the ML Helpdesk pilot (Design Doc Table 30).
Deterministic (fixed seed) and self-checking against the business rules."""
import random, sys
from datetime import date, datetime, timedelta

OUT = sys.argv[1]
PW_HASH = sys.argv[2]          # bcrypt hash of the pilot password
NOW = datetime(2026, 11, 1, 18, 0)   # nothing in the pilot data is later than this
rnd = random.Random(2026)

def q(v):
    if v is None: return "NULL"
    if isinstance(v, bool): return "TRUE" if v else "FALSE"
    if isinstance(v, (int, float)): return str(v)
    if isinstance(v, datetime): return "'" + v.strftime("%Y-%m-%d %H:%M:%S") + "'"
    if isinstance(v, date): return "'" + v.isoformat() + "'"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"

out = []
def insert(table, cols, rows):
    out.append(f"INSERT INTO {table} ({', '.join(cols)}) VALUES")
    out.append(",\n".join("  (" + ", ".join(q(v) for v in r) + ")" for r in rows) + ";\n")

# ---------------------------------------------------------------- Department
DEPTS = ["Finance", "Legal", "Sales", "Operations", "HR", "IT", "Projects", "Administration"]
D = {n: i + 1 for i, n in enumerate(DEPTS)}

# ---------------------------------------------------------------- Office (15, all 7 specialisations)
OFFICES = [
    ("Lahore", "21 Main Boulevard, Gulberg III", "Lahore", "54660", "042-3575 1100", "Property Management"),
    ("Karachi", "Plot 7, Shahrah-e-Faisal", "Karachi", "75350", "021-3456 7788", "Brokerage"),
    ("Islamabad", "Office 4, Blue Area", "Islamabad", "44000", "051-2870 4400", "Legal"),
    ("Rawalpindi", "12 Murree Road", "Rawalpindi", "46000", "051-5551 2200", "Lending"),
    ("Faisalabad", "88 Susan Road", "Faisalabad", "38000", "041-8711 3300", "Construction"),
    ("Multan", "5 Bosan Road", "Multan", "60000", "061-6512 4400", "Land Development"),
    ("Peshawar", "3 University Road", "Peshawar", "25000", "091-5842 5500", "Interior Design"),
    ("Quetta", "19 Jinnah Road", "Quetta", "87300", "081-2823 6600", "Land Development"),
    ("Sialkot", "44 Paris Road", "Sialkot", "51310", "052-4265 7700", "Construction"),
    ("Gujranwala", "9 GT Road", "Gujranwala", "52250", "055-3731 8800", "Property Management"),
    ("Hyderabad", "27 Auto Bhan Road", "Hyderabad", "71000", "022-2786 9900", "Brokerage"),
    ("Bahawalpur", "15 Circular Road", "Bahawalpur", "63100", "062-2883 1010", "Lending"),
    ("Sargodha", "6 University Road", "Sargodha", "40100", "048-3726 2020", "Interior Design"),
    ("Abbottabad", "2 Mansehra Road", "Abbottabad", "22010", "099-2334 3030", "Legal"),
    ("Sukkur", "11 Military Road", "Sukkur", "65200", "071-5631 4040", "Construction"),
]

# ---------------------------------------------------------------- Employee (60 = 4 per office)
# (ID, first, last, job title, office no., department, start, end)
EMP = [
    ("E0045", "Bilal", "Ahmed", "Property Manager", 1, "Operations", "2020-02-03", None),
    ("E0002", "Sana", "Iqbal", "Office Administrator", 1, "Administration", "2019-06-10", None),
    ("E0003", "Ayesha", "Khan", "Helpdesk Operator", 1, "IT", "2024-11-04", None),
    ("E0004", "Usman", "Tariq", "IT Technician", 1, "IT", "2021-03-15", None),
    ("E0005", "Ali", "Raza", "Senior Broker", 2, "Sales", "2018-09-01", None),
    ("E0006", "Imran", "Shah", "Office Manager", 2, "Administration", "2016-04-11", None),
    ("E0007", "Omar", "Farooq", "Helpdesk Operator", 2, "IT", "2024-11-04", None),
    ("E0008", "Hamza", "Ali", "IT Technician", 2, "IT", "2019-01-07", None),
    ("E0009", "Hira", "Baig", "Legal Counsel", 3, "Legal", "2020-08-17", None),
    ("E0010", "Sara", "Malik", "IT Technician", 3, "IT", "2022-05-02", None),
    ("E0011", "Nida", "Hussain", "Helpdesk Operator", 3, "IT", "2024-11-04", None),
    ("E0012", "Zara", "Noor", "Paralegal", 3, "Legal", "2020-01-06", "2025-06-30"),
    ("E0013", "Waqas", "Anwar", "System Administrator", 4, "IT", "2017-03-20", None),
    ("E0014", "Faisal", "Mehmood", "Helpdesk Operator", 4, "IT", "2024-11-04", None),
    ("E0015", "Ahsan", "Butt", "Loan Officer", 4, "Finance", "2021-07-12", None),
    ("E0016", "Maryam", "Rauf", "Credit Analyst", 4, "Finance", "2022-02-14", None),
    ("E0017", "Kamran", "Javed", "IT Technician", 5, "IT", "2020-10-05", None),
    ("E0018", "Rabia", "Aslam", "Helpdesk Operator", 5, "IT", "2024-11-04", None),
    ("E0019", "Shahid", "Latif", "Site Engineer", 5, "Projects", "2019-05-27", None),
    ("E0020", "Amna", "Saeed", "Project Coordinator", 5, "Projects", "2023-01-09", None),
    ("E0021", "Mehwish", "Qureshi", "IT Technician", 6, "IT", "2021-11-01", None),
    ("E0022", "Farah", "Naz", "Office Manager", 6, "Administration", "2015-09-14", None),
    ("E0023", "Junaid", "Akhtar", "Land Surveyor", 6, "Projects", "2018-03-05", None),
    ("E0024", "Saima", "Riaz", "Accountant", 6, "Finance", "2020-06-22", None),
    ("E0025", "Adnan", "Siddiqui", "IT Technician", 7, "IT", "2022-09-19", None),
    ("E0026", "Tariq", "Mahmood", "Office Manager", 7, "Administration", "2014-02-03", None),
    ("E0027", "Gul", "Khan", "Interior Designer", 7, "Projects", "2019-11-11", None),
    ("E0028", "Laiba", "Shah", "Design Assistant", 7, "Projects", "2023-04-03", "2026-02-28"),
    ("E0029", "Naveed", "Kakar", "Land Development Officer", 8, "Operations", "2017-08-21", None),
    ("E0030", "Asma", "Durrani", "HR Officer", 8, "HR", "2021-01-18", None),
    ("E0031", "Bilawal", "Marri", "Surveyor", 8, "Projects", "2022-06-06", None),
    ("E0032", "Sadia", "Baloch", "Receptionist", 8, "Administration", "2019-02-25", "2025-12-31"),
    ("E0033", "Arslan", "Cheema", "Site Manager", 9, "Projects", "2016-10-10", None),
    ("E0034", "Iqra", "Malik", "Quantity Surveyor", 9, "Finance", "2021-04-26", None),
    ("E0035", "Hassan", "Raza", "Site Engineer", 9, "Projects", "2020-03-02", "2026-09-15"),
    ("E0036", "Mahnoor", "Ali", "Admin Assistant", 9, "Administration", "2023-08-14", None),
    ("E0037", "Zeeshan", "Haider", "Property Manager", 10, "Operations", "2018-12-03", None),
    ("E0038", "Fatima", "Zahid", "Leasing Officer", 10, "Sales", "2022-03-21", None),
    ("E0039", "Kashif", "Nawaz", "Maintenance Supervisor", 10, "Operations", "2019-07-15", None),
    ("E0040", "Hina", "Akram", "Accountant", 10, "Finance", "2020-11-30", None),
    ("E0041", "Danish", "Memon", "Broker", 11, "Sales", "2017-05-08", None),
    ("E0042", "Sundas", "Qazi", "Sales Executive", 11, "Sales", "2023-02-20", None),
    ("E0043", "Ravi", "Kumar", "Market Analyst", 11, "Sales", "2021-09-13", None),
    ("E0044", "Anum", "Soomro", "HR Officer", 11, "HR", "2022-10-03", None),
    ("E0001", "Atif", "Rehman", "Branch Manager", 12, "Finance", "2012-01-09", None),
    ("E0046", "Sobia", "Khalid", "Loan Officer", 12, "Finance", "2020-04-06", None),
    ("E0047", "Rizwan", "Abbasi", "Credit Officer", 12, "Finance", "2019-08-19", None),
    ("E0048", "Nadia", "Sheikh", "Admin Officer", 12, "Administration", "2018-06-11", "2026-05-31"),
    ("E0049", "Ayaan", "Chaudhry", "Interior Designer", 13, "Projects", "2021-12-06", None),
    ("E0050", "Mariam", "Javed", "Design Lead", 13, "Projects", "2017-09-25", None),
    ("E0051", "Umer", "Farooqi", "Procurement Officer", 13, "Finance", "2022-07-18", None),
    ("E0052", "Sehrish", "Anjum", "Office Administrator", 13, "Administration", "2020-02-17", None),
    ("E0053", "Osama", "Jadoon", "Legal Advisor", 14, "Legal", "2018-04-30", None),
    ("E0054", "Rimsha", "Abbasi", "Paralegal", 14, "Legal", "2023-06-12", None),
    ("E0055", "Haris", "Swati", "Solicitor", 14, "Legal", "2016-11-21", None),
    ("E0056", "Kiran", "Tanoli", "HR Assistant", 14, "HR", "2024-01-15", None),
    ("E0057", "Yasir", "Mahar", "Project Manager", 15, "Projects", "2015-03-16", None),
    ("E0058", "Shazia", "Shaikh", "Site Engineer", 15, "Projects", "2021-08-02", None),
    ("E0059", "Imtiaz", "Jatoi", "Health and Safety Officer", 15, "Operations", "2019-10-28", None),
    ("E0060", "Bushra", "Kalhoro", "Accountant", 15, "Finance", "2022-12-05", None),
]
assert len(EMP) == 60 and len({e[0] for e in EMP}) == 60
E = {e[0]: dict(id=e[0], first=e[1], last=e[2], office=e[4],
                start=date.fromisoformat(e[6]),
                end=date.fromisoformat(e[7]) if e[7] else None) for e in EMP}

def employed(eid, d):
    e = E[eid]
    return e["start"] <= d and (e["end"] is None or e["end"] >= d)

# ---------------------------------------------------------------- AppUser (15)
USERS = [  # (username, role, employee, active, failed)
    ("a.khan", "Operator", "E0003", True, 0), ("o.farooq", "Operator", "E0007", True, 0),
    ("n.hussain", "Operator", "E0011", True, 0), ("f.mehmood", "Operator", "E0014", True, 0),
    ("r.aslam", "Operator", "E0018", False, 5),          # locked after 5 failed log-ins
    ("h.ali", "Technician", "E0008", True, 0), ("s.malik", "Technician", "E0010", True, 0),
    ("u.tariq", "Technician", "E0004", True, 0), ("k.javed", "Technician", "E0017", True, 0),
    ("m.qureshi", "Technician", "E0021", True, 0), ("a.siddiqui", "Technician", "E0025", True, 0),
    ("i.shah", "Manager", "E0006", True, 0), ("f.naz", "Manager", "E0022", True, 0),
    ("t.mahmood", "Manager", "E0026", True, 0), ("w.anwar", "Admin", "E0013", True, 0),
]
UID = {u[0]: i + 1 for i, u in enumerate(USERS)}
OPERATORS = [UID[u[0]] for u in USERS if u[1] == "Operator"]

# ---------------------------------------------------------------- Technician (6) + specialisms (18)
TECHS = ["E0008", "E0010", "E0004", "E0017", "E0021", "E0025"]   # TechnicianID 1..6
TECH_AVAILABLE = [True, True, True, False, True, True]          # Kamran Javed on leave
TECH_USER = {1: UID["h.ali"], 2: UID["s.malik"], 3: UID["u.tariq"],
             4: UID["k.javed"], 5: UID["m.qureshi"], 6: UID["a.siddiqui"]}
PTYPES = [("PC", "Hardware"), ("Laptop", "Hardware"), ("Printer", "Hardware"),
          ("Peripheral", "Hardware"), ("Interactive Whiteboard", "Hardware"),
          ("Router", "Hardware"), ("Switch", "Hardware"), ("Wireless Access Point", "Hardware"),
          ("Firewall", "Hardware"), ("Cabling", "Hardware"),
          ("Office software", "Software"), ("Operating system", "Software"),
          ("Time management software", "Software"), ("Project management software", "Software")]
SPEC = {1: [11, 6, 2, 9], 2: [11, 12, 1, 13], 3: [11, 2, 3], 4: [3], 5: [7, 8, 10], 6: [4, 5, 14]}
assert sum(len(v) for v in SPEC.values()) == 18
assert set(range(1, 15)) == {t for v in SPEC.values() for t in v}   # every type covered
SKILLED = {pt: [t for t, v in SPEC.items() if pt in v] for pt in range(1, 15)}

# ---------------------------------------------------------------- Manufacturer / EquipmentType (10 / 10)
MANS = [("HP Inc.", "0800-0800 4747", "https://support.hp.com"),
        ("Dell Technologies", "0800-0900 3355", "https://www.dell.com/support"),
        ("Lenovo", "0800-0900 5366", "https://support.lenovo.com"),
        ("Cisco Systems", "0800-0900 2472", "https://www.cisco.com/c/en/us/support"),
        ("Fortinet", "+1 408 486 7899", "https://support.fortinet.com"),
        ("Microsoft", "0800-0900 6427", "https://support.microsoft.com"),
        ("Epson", "0800-0900 3776", "https://epson.com/support"),
        ("Promethean", "+44 1254 298598", "https://support.prometheanworld.com"),
        ("Logitech", "0800-0900 5644", "https://support.logi.com"),
        ("Zoho Corporation", "+1 888 900 9646", "https://help.zoho.com")]
M = {n[0]: i + 1 for i, n in enumerate(MANS)}
ETYPES = [("PC", "Office"), ("Laptop", "Office"), ("Printer", "Office"), ("Peripheral", "Office"),
          ("Interactive Whiteboard", "Office"), ("Router", "Network"), ("Switch", "Network"),
          ("Wireless Access Point", "Network"), ("Firewall", "Network"), ("Cable", "Network")]

# ---------------------------------------------------------------- Software (8) / licences (12)
SOFT = [("Windows", "11 Pro", "Operating System", "Microsoft"),
        ("Windows", "10 Pro", "Operating System", "Microsoft"),
        ("Microsoft Excel", "365", "Spreadsheet", "Microsoft"),
        ("Microsoft Word", "365", "Word Processor", "Microsoft"),
        ("Microsoft PowerPoint", "365", "Presentation", "Microsoft"),
        ("Microsoft Outlook", "365", "Time Management", "Microsoft"),
        ("Microsoft Project", "2021", "Project Management", "Microsoft"),
        ("Zoho Projects", "Cloud", "Project Management", "Zoho Corporation")]
LICS = [("LIC-W11-0045", 1, "Volume", 50, "2025-01-01", None),
        ("LIC-W10-0012", 2, "Volume", 20, "2021-01-01", None),
        ("LIC-O365-0110", 3, "Subscription", 60, "2026-01-01", "2026-12-31"),
        ("LIC-O365-0099", 3, "Subscription", 60, "2025-01-01", "2025-12-31"),
        ("LIC-WRD-0111", 4, "Subscription", 60, "2026-01-01", "2026-12-31"),
        ("LIC-WRD-0098", 4, "Subscription", 60, "2025-01-01", "2025-12-31"),
        ("LIC-PPT-0112", 5, "Subscription", 30, "2025-01-01", "2025-12-31"),
        ("LIC-OUT-0113", 6, "Subscription", 60, "2026-01-01", "2026-12-31"),
        ("LIC-PRJ-0201", 7, "Perpetual", 10, "2023-05-01", None),
        ("LIC-PRJ-0202", 7, "Perpetual", 5, "2024-02-01", None),
        ("LIC-ZOH-0301", 8, "Subscription", 15, "2025-07-01", "2026-06-30"),
        ("LIC-W10-0013", 2, "OEM", 1, "2020-09-01", None)]

# ---------------------------------------------------------------- Hardware (40)
# (serial, type id, manufacturer, make, model, purchase, warranty years, office, assigned)
HW = [
    ("HP-5CG1234XYZ", 2, "HP Inc.", "EliteBook", "840 G9", "2025-03-15", 3, 1, "E0045"),
    ("CS-FGT60F-0021", 9, "Fortinet", "FortiGate", "60F", "2024-08-01", 3, 1, None),
    ("DL-7XQ2M93", 1, "Dell Technologies", "OptiPlex", "7010", "2022-01-10", 3, 2, "E0005"),
    ("EP-L3150-0042", 3, "Epson", "EcoTank", "L3150", "2021-05-20", 2, 2, None),
    ("LN-PF3XK2", 2, "Lenovo", "ThinkPad", "E14", "2024-06-01", 3, 3, "E0009"),
]
COMPUTER_MODELS = [(1, "Dell Technologies", "OptiPlex", "7010", "DL"),
                   (1, "HP Inc.", "ProDesk", "400 G9", "HP"),
                   (2, "Lenovo", "ThinkPad", "E14", "LN"),
                   (2, "HP Inc.", "ProBook", "450 G10", "HP"),
                   (2, "Dell Technologies", "Latitude", "5440", "DL")]
# one more computer for every office (offices 1-15)
for o in range(1, 16):
    t, man, make, model, pfx = COMPUTER_MODELS[o % len(COMPUTER_MODELS)]
    staff = [e for e in EMP if e[4] == o and e[7] is None
             and e[0] not in {h[8] for h in HW}]
    y = 2021 + (o % 5)
    HW.append((f"{pfx}-{rnd.randint(1000000, 9999999)}", t, man, make, model,
               f"{y}-{(o % 12) + 1:02d}-{(o * 3 % 27) + 1:02d}", 3, o, staff[0][0]))
OTHER = [  # 20 more: printers, peripherals, whiteboards and network kit
    (3, "HP Inc.", "LaserJet Pro", "M404dn", "HP", 1), (3, "Epson", "EcoTank", "L6270", "EP", 3),
    (3, "HP Inc.", "LaserJet Pro", "M404dn", "HP", 6), (3, "Epson", "WorkForce", "WF-C5790", "EP", 10),
    (4, "Logitech", "MX Keys", "Combo", "LG", 1), (4, "Logitech", "Brio", "4K Webcam", "LG", 3),
    (4, "Logitech", "C925e", "Webcam", "LG", 7),
    (5, "Promethean", "ActivPanel", "Titanium 75", "PM", 7), (5, "Promethean", "ActivPanel", "Nickel 65", "PM", 13),
    (6, "Cisco Systems", "ISR", "1111-8P", "CS", 2), (6, "Cisco Systems", "ISR", "1111-8P", "CS", 4),
    (6, "Cisco Systems", "ISR", "1121-8P", "CS", 9),
    (7, "Cisco Systems", "Catalyst", "1000-24T", "CS", 1), (7, "Cisco Systems", "Catalyst", "1000-24T", "CS", 2),
    (7, "Cisco Systems", "Catalyst", "1000-16T", "CS", 11),
    (8, "Cisco Systems", "Aironet", "1815i", "CS", 3), (8, "Cisco Systems", "Aironet", "1815i", "CS", 5),
    (9, "Fortinet", "FortiGate", "40F", "FT", 2),
    (10, "Cisco Systems", "Cat6 Patch", "30m Bundle", "CS", 6),
    (4, "Logitech", "MX Master", "3S Mouse", "LG", 12),
]
for i, (t, man, make, model, pfx, o) in enumerate(OTHER):
    y = 2020 + (i % 6)
    HW.append((f"{pfx}-{make[:3].upper().replace(' ', '')}-{1000 + i * 37}", t, man, make, model,
               f"{y}-{(i % 12) + 1:02d}-{(i * 5 % 27) + 1:02d}", 2 if t in (3, 4) else 3, o, None))
assert len(HW) == 40 and len({h[0] for h in HW}) == 40

def add_years(d, n):
    try: return d.replace(year=d.year + n) - timedelta(days=1)
    except ValueError: return d.replace(year=d.year + n, day=28)

HWD = []   # dicts with ids
for i, h in enumerate(HW):
    pd = date.fromisoformat(h[5])
    HWD.append(dict(id=i + 1, serial=h[0], type=h[1], man=M[h[2]], make=h[3], model=h[4],
                    purchase=pd, warranty=add_years(pd, h[6]), office=h[7], emp=h[8]))
    assert pd <= NOW.date()
COMPUTERS = [h for h in HWD if h["type"] in (1, 2)]

# ---------------------------------------------------------------- InstalledSoftware (50)
INST = []
OS_OF = {}
for i, c in enumerate(COMPUTERS):                     # 18 computers
    lic = 12 if i == 2 else (1 if c["purchase"].year >= 2023 or i == 0 else 2)
    OS_OF[c["id"]] = LICS[lic - 1][1]                 # software id of the OS
    INST.append((c["id"], lic, max(c["purchase"], date.fromisoformat(LICS[lic - 1][4]))))
extra = [(3, 14), (5, 10), (8, 5), (9, 2), (7, 1)]     # (licence, how many computers)
for lic, n in extra:
    for c in COMPUTERS[:n]:
        INST.append((c["id"], lic, max(c["purchase"], date.fromisoformat(LICS[lic - 1][4]))))
assert len(INST) == 50 and len({(a, b) for a, b, _ in INST}) == 50
APPS_ON = {}
for hid, lic, _ in INST:
    sid = LICS[lic - 1][1]
    if sid not in (1, 2): APPS_ON.setdefault(hid, []).append(sid)

# ---------------------------------------------------------------- Problems (60) and calls (90)
DESC = {
    1: ["PC will not start, power light flashes amber.", "PC very slow and fan is very loud."],
    2: ["Laptop battery not charging when plugged in.", "Laptop screen flickers after docking."],
    3: ["Printer jams on every second page.", "Printer shows offline for all users.",
        "Print quality poor, streaks on every page.", "Paper feed error E-03 on printer."],
    4: ["Keyboard keys not responding after spill.", "Webcam not detected in Teams meetings."],
    5: ["Interactive whiteboard touch is not aligned.", "Whiteboard panel will not power on."],
    6: ["No internet in whole office since morning.", "Router keeps rebooting every hour."],
    7: ["Half of the desks lost network connection.", "Switch port lights off on floor two."],
    8: ["Wi-Fi drops every few minutes in meeting room.", "Guests cannot join the guest Wi-Fi."],
    9: ["VPN users cannot connect through firewall.", "Firewall blocking client portal website."],
    10: ["Network cable damaged under desk, no link.", "Loose patch cable at reception desk."],
    11: ["Excel crashes when opening shared workbook.", "Word document will not save to network drive.",
         "PowerPoint freezes when inserting video."],
    12: ["Windows update stuck at 35 percent.", "Blue screen error after Windows update."],
    13: ["Outlook calendar not syncing with phone.", "Meeting reminders not appearing in Outlook."],
    14: ["Project file will not open, says corrupted.", "Gantt chart not updating task dates."],
}
FIX = {
    1: "Replaced faulty power supply unit and tested.", 2: "Updated firmware and replaced battery under warranty.",
    3: "Cleaned rollers, replaced pickup roller and tested.", 4: "Replaced device and installed driver.",
    5: "Recalibrated touch and updated panel firmware.", 6: "Restarted router, updated firmware and checked ISP line.",
    7: "Replaced faulty switch module and re-patched ports.", 8: "Changed Wi-Fi channel and updated access point firmware.",
    9: "Updated firewall policy and restarted VPN service.", 10: "Replaced damaged cable and tested link.",
    11: "Repaired Office installation and cleared cached credentials.",
    12: "Rolled back faulty update and reinstalled it cleanly.",
    13: "Re-created Outlook profile and re-synced calendar.", 14: "Restored file from backup and repaired project file.",
}
REASONS_FU = [("Chasing progress - still not fixed", "Urgent"),
              ("Caller distressed - deadline today", "Distressed"),
              ("Problem came back after fix", "Urgent"),
              ("Asking for expected fix time", "Normal"),
              ("Adding more details about the fault", "Normal")]

def work_time(d0, d1):
    while True:
        d = d0 + timedelta(days=rnd.randint(0, (d1 - d0).days))
        if d.weekday() < 6:   # Monday - Saturday
            return datetime(d.year, d.month, d.day, rnd.randint(8, 16), rnd.choice([0, 10, 15, 25, 30, 40, 45, 55]))

# forced cases so the reports show something worth finding
FORCED = [  # (hardware serial, problem type, reporter, (y,m,d,h,mi))
    ("EP-L3150-0042", 3, "E0005", (2026, 8, 6, 10, 15)), ("EP-L3150-0042", 3, "E0006", (2026, 9, 2, 9, 40)),
    ("EP-L3150-0042", 3, "E0005", (2026, 10, 7, 11, 5)), ("EP-L3150-0042", 3, "E0006", (2026, 10, 28, 14, 30)),
    ("HP-5CG1234XYZ", 2, "E0045", (2026, 8, 19, 9, 20)), ("HP-5CG1234XYZ", 11, "E0045", (2026, 10, 2, 10, 42)),
    ("HP-5CG1234XYZ", 11, "E0045", (2026, 10, 30, 15, 10)),
    ("CS-FGT60F-0021", 9, "E0002", (2026, 9, 10, 8, 50)), ("CS-FGT60F-0021", 9, "E0045", (2026, 10, 3, 9, 5)),
    (None, 1, "E0035", (2026, 8, 12, 12, 0)),          # ex-employee (left 15/09/2026) - history kept
]
SERIAL = {h["serial"]: h for h in HWD}

problems = []
def make_problem(hw, ptype, reporter, logged):
    sw = os_ = None
    if hw and hw["type"] in (1, 2):
        os_ = OS_OF[hw["id"]]
        apps = APPS_ON.get(hw["id"], [])
        if ptype == 11: sw = next((s for s in apps if s in (3, 4, 5)), 3)
        elif ptype == 13: sw = 6
        elif ptype == 14: sw = next((s for s in apps if s in (7, 8)), 7)
    choices = [t for t in SKILLED[ptype] if TECH_AVAILABLE[t - 1] or logged < datetime(2026, 10, 1)]
    problems.append(dict(hw=hw["id"] if hw else None, ptype=ptype, rep=reporter, logged=logged,
                         os=os_, sw=sw, tech=rnd.choice(choices)))

for serial, pt, rep, t in FORCED:
    hw = SERIAL[serial] if serial else next(h for h in COMPUTERS if h["office"] == E[rep]["office"])
    make_problem(hw, pt, rep, datetime(*t))

while len(problems) < 60:
    hw = rnd.choice(HWD)
    logged = work_time(date(2026, 8, 1), date(2026, 10, 31))
    staff = [e for e in E if E[e]["office"] == hw["office"] and employed(e, logged.date())]
    rep = hw["emp"] if hw["emp"] and employed(hw["emp"], logged.date()) else rnd.choice(staff)
    pt = hw["type"]
    if hw["type"] in (1, 2) and rnd.random() < 0.55:
        pt = rnd.choice([11, 11, 12, 13, 14])
    make_problem(hw, pt, rep, logged)

problems.sort(key=lambda p: p["logged"])
# two newest problems are left unassigned to show the Assign Technician screen
for p in problems[-2:]:
    p["tech"] = None

PRIOS = ["Low", "Medium", "Medium", "Medium", "High", "High", "Critical"]
prob_rows, call_rows = [], []
for n, p in enumerate(problems, start=1):
    p["no"] = n
    lg = p["logged"]
    assigned = lg + timedelta(minutes=rnd.randint(5, 40)) if p["tech"] else None
    closed = p["tech"] is not None and (lg < datetime(2026, 10, 15) or rnd.random() < 0.45)
    if lg < datetime(2026, 10, 15) and rnd.random() < 0.06:
        closed = False                                  # a few old jobs still open
    res = notes = hours = resolver = None
    if closed:
        res = lg + timedelta(hours=rnd.choice([1, 2, 3, 4, 6, 20, 26, 48, 72]), minutes=rnd.randint(0, 50))
        if res > NOW: res = NOW
        elapsed = (res - lg).total_seconds() / 3600
        hours = min(rnd.choice([0.5, 0.75, 1, 1.5, 2, 2.5, 3, 3.5, 4, 5, 6, 8]), max(0.25, int(elapsed * 4) / 4))
        notes = FIX[p["ptype"]]
        resolver = TECH_USER[p["tech"]] if rnd.random() < 0.7 else rnd.choice(OPERATORS[:4])
        assert res > lg and 0.25 <= hours <= 500 and res <= NOW
    p.update(closed=closed, res=res)
    pr = rnd.choice(PRIOS) if p["ptype"] not in (6, 9) else "Critical"
    prob_rows.append((n, p["rep"], p["ptype"], p["hw"], p["os"], p["sw"],
                      rnd.choice(DESC[p["ptype"]]), pr, "Closed" if closed else "Open", lg,
                      p["tech"], assigned, res, notes, hours, resolver))
    # business-rule self checks (mirror the database triggers)
    assert employed(p["rep"], lg.date()), p
    if p["tech"]: assert p["ptype"] in SPEC[p["tech"]]
    # first call
    call_rows.append([n, p["rep"], rnd.choice(OPERATORS), lg, "New",
                      DESC[p["ptype"]][0].rstrip("."), rnd.choice(["Normal", "Normal", "Urgent"]), None])

# 30 follow-up calls -> 90 calls in total
fu = rnd.sample(problems, 30)
for p in sorted(fu, key=lambda x: x["no"]):
    lg = p["logged"]
    last = p["res"] - timedelta(minutes=10) if p["res"] else min(lg + timedelta(hours=48), NOW)
    if last <= lg + timedelta(minutes=30):
        last = lg + timedelta(minutes=45)
    t = lg + timedelta(seconds=rnd.randint(1800, int((last - lg).total_seconds())))
    t = t.replace(second=0)
    reason, urg = rnd.choice(REASONS_FU)
    caller = p["rep"] if employed(p["rep"], t.date()) else None
    assert caller and t <= NOW
    call_rows.append([p["no"], caller, rnd.choice(OPERATORS), t, "Follow-up", reason, urg,
                      "Caller quoted " + f"P-{p['no']:06d}"])
call_rows.sort(key=lambda c: c[3])
assert len(prob_rows) == 60 and len(call_rows) == 90

# ---------------------------------------------------------------- write the SQL
out.append("""-- =====================================================================
-- ML IT Helpdesk System  |  Script 03 of 06: pilot data
-- Core pilot data set from Design Document Section 7.5 (Table 30).
-- Run after 02_views_triggers_procedures.sql. Data is fictitious.
-- Pilot log-in password for every AppUser account: Helpdesk2026
--   (stored only as a bcrypt hash; change it before go-live)
-- =====================================================================
USE mlhelpdesk;
SET NAMES utf8mb4;
START TRANSACTION;
""")
out.append("-- Departments (8)")
insert("Department", ["DepartmentName"], [(d,) for d in DEPTS])
out.append("-- Offices (15) - every specialisation is used")
insert("Office", ["OfficeName", "AddressLine", "City", "Postcode", "ContactPhone", "Specialisation"], OFFICES)
out.append("-- Employees (60): 4 per office, incl. 5 ex-employees, 5 operators, 6 technicians, 3 managers, 1 admin")
insert("Employee", ["EmployeeID", "FirstName", "LastName", "JobTitle", "ContactPhone", "Email",
                    "StartDate", "EndDate", "OfficeID", "DepartmentID"],
       [(e[0], e[1], e[2], e[3], f"03{(int(e[0][1:]) * 7) % 50 + 00:02d}-{5550000 + int(e[0][1:]) * 1371}",
         f"{e[1].lower()}.{e[2].lower()}@ml.co.uk", e[6], e[7], e[4], D[e[5]]) for e in EMP])
out.append("-- Application users (15). r.aslam is locked to show the 5-failed-log-in rule")
insert("AppUser", ["Username", "PasswordHash", "Role", "IsActive", "FailedLogins", "EmployeeID"],
       [(u[0], PW_HASH, u[1], u[3], u[4], u[2]) for u in USERS])
out.append("-- Technicians (6). Kamran Javed is on leave (IsAvailable = FALSE)")
insert("Technician", ["EmployeeID", "IsAvailable"], list(zip(TECHS, TECH_AVAILABLE)))
out.append("-- Problem types (14): 10 hardware, 4 software")
insert("ProblemType", ["TypeName", "FaultCategory"], PTYPES)
out.append("-- Technician specialisms (18) - overlapping so load balancing can be tested")
insert("TechnicianSpecialism", ["TechnicianID", "ProblemTypeID"],
       [(t, p) for t, v in SPEC.items() for p in v])
out.append("-- Manufacturers (10)")
insert("Manufacturer", ["ManufacturerName", "SupportPhone", "Website"], MANS)
out.append("-- Equipment types (10)")
insert("EquipmentType", ["TypeName", "HardwareCategory"], ETYPES)
out.append("-- Hardware register (40): office and network kit, some warranties expired")
insert("Hardware", ["SerialNumber", "EquipmentTypeID", "ManufacturerID", "Make", "Model",
                    "PurchaseDate", "WarrantyExpiry", "OfficeID", "AssignedToEmployeeID"],
       [(h["serial"], h["type"], h["man"], h["make"], h["model"], h["purchase"], h["warranty"],
         h["office"], h["emp"]) for h in HWD])
out.append("-- Software products (8)")
insert("Software", ["SoftwareName", "Version", "SoftwareCategory", "ManufacturerID"],
       [(s[0], s[1], s[2], M[s[3]]) for s in SOFT])
out.append("-- Software licences (12): perpetual, subscription, volume and OEM; some expired")
insert("SoftwareLicence", ["LicenceNumber", "SoftwareID", "LicenceType", "Seats", "StartDate", "ExpiryDate"], LICS)
out.append("-- Installed software (50)")
insert("InstalledSoftware", ["HardwareID", "LicenceID", "InstallDate"], INST)
out.append("-- Problems (60) logged August-October 2026, open and closed")
insert("Problem", ["ProblemNumber", "ReportedByEmployeeID", "ProblemTypeID", "HardwareID", "OSSoftwareID",
                   "SoftwareID", "Description", "Priority", "Status", "DateTimeLogged", "TechnicianID",
                   "DateTimeAssigned", "DateTimeResolved", "ResolutionNotes", "TimeTakenHours",
                   "ResolvedByUserID"], prob_rows)
out.append("-- Helpdesk calls (90): 60 new calls + 30 follow-up calls")
insert("HelpdeskCall", ["ProblemNumber", "CallerEmployeeID", "OperatorUserID", "CallDateTime",
                        "CallType", "CallReason", "UrgencyLevel", "Notes"], call_rows)
out.append("-- Maintenance history (8): UserID NULL = scheduled nightly backup")
insert("MaintenanceLog", ["UserID", "ActionType", "ActionDateTime", "BackupFile", "Outcome"], [
    (UID["a.khan"], "Backup", datetime(2026, 10, 29, 18, 0), "mlhelpdesk_20261029_1800.sql", "Success"),
    (UID["a.khan"], "Restore", datetime(2026, 10, 30, 9, 12), "mlhelpdesk_20261029_1800.sql", "Success"),
    (UID["a.khan"], "Compact", datetime(2026, 10, 31, 19, 5), None, "Success"),
    (UID["i.shah"], "Backup", datetime(2026, 11, 1, 18, 0), "mlhelpdesk_20261101_1800.sql", "Success"),
    (UID["h.ali"], "Backup", datetime(2026, 10, 15, 18, 0), "mlhelpdesk_20261015_1800.sql", "Failed"),
    (UID["o.farooq"], "Backup", datetime(2026, 10, 16, 18, 0), "mlhelpdesk_20261016_1800.sql", "Success"),
    (None, "Backup", datetime(2026, 10, 30, 18, 0), "mlhelpdesk_20261030_1800.sql", "Success"),
    (UID["w.anwar"], "Check", datetime(2026, 10, 1, 7, 0), None, "Success"),
])
out.append("COMMIT;\n")
out.append("""-- Quick check: row counts (compare with Design Document Table 30)
SELECT 'Office' AS TableName, COUNT(*) AS PilotRows FROM Office
UNION ALL SELECT 'Department', COUNT(*) FROM Department
UNION ALL SELECT 'Employee', COUNT(*) FROM Employee
UNION ALL SELECT 'AppUser', COUNT(*) FROM AppUser
UNION ALL SELECT 'Technician', COUNT(*) FROM Technician
UNION ALL SELECT 'TechnicianSpecialism', COUNT(*) FROM TechnicianSpecialism
UNION ALL SELECT 'ProblemType', COUNT(*) FROM ProblemType
UNION ALL SELECT 'Manufacturer', COUNT(*) FROM Manufacturer
UNION ALL SELECT 'EquipmentType', COUNT(*) FROM EquipmentType
UNION ALL SELECT 'Hardware', COUNT(*) FROM Hardware
UNION ALL SELECT 'Software', COUNT(*) FROM Software
UNION ALL SELECT 'SoftwareLicence', COUNT(*) FROM SoftwareLicence
UNION ALL SELECT 'InstalledSoftware', COUNT(*) FROM InstalledSoftware
UNION ALL SELECT 'Problem', COUNT(*) FROM Problem
UNION ALL SELECT 'HelpdeskCall', COUNT(*) FROM HelpdeskCall
UNION ALL SELECT 'MaintenanceLog', COUNT(*) FROM MaintenanceLog;
""")
with open(OUT, "w") as f:
    f.write("\n".join(out))
print("ok", len(prob_rows), "problems", len(call_rows), "calls",
      sum(1 for r in prob_rows if r[8] == "Closed"), "closed")
