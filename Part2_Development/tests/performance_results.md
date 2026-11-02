# Performance test results (NFR-01)

Run on 02/11/2026 15:08 with **50,061 problem records** and the same number of calls (MySQL 8.0, PHP 8.2, pilot laptop). Each test was run 3 times; *Slowest* is the figure checked against the limit.

| Req. | Test | MySQL account | Rows returned | Average (s) | Slowest (s) | Limit (s) | Result |
|---|---|---|---:|---:|---:|---:|---|
| UR-02 | Caller search by partial name | ml_operator | 2 | 0.001 | 0.002 | 2 | PASS |
| UR-03 | Equipment look-up by serial + warranty | ml_operator | 1 | 0.001 | 0.001 | 2 | PASS |
| UR-04 | Installed software + licence status | ml_operator | 6 | 0.001 | 0.001 | 2 | PASS |
| UR-08 | Skilled technicians with open-job count | ml_operator | 3 | 0.003 | 0.004 | 2 | PASS |
| UR-06 | Find problem by number (follow-up) | ml_operator | 1 | 0.001 | 0.002 | 2 | PASS |
| UR-12 | Report R1 | ml_manager | 3,457 | 0.095 | 0.103 | 5 | PASS |
| UR-12 | Report R2 | ml_manager | 7 | 0.085 | 0.086 | 5 | PASS |
| UR-12 | Report R3 | ml_manager | 15 | 0.086 | 0.087 | 5 | PASS |
| UR-12 | Report R3 (all time) | ml_manager | 15 | 0.165 | 0.166 | 5 | PASS |
| UR-12 | Report R4 | ml_manager | 48 | 0.435 | 0.444 | 5 | PASS |
| UR-12 | Report R5 | ml_manager | 721 | 0.050 | 0.053 | 5 | PASS |
| UR-12 | Report R6 | ml_manager | 345 | 0.955 | 0.984 | 5 | PASS |

**12 of 12 tests within the NFR-01 limits.** The generated rows were deleted after the test.
