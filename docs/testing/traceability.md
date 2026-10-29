# Requirements and implementation traceability

One row is a test design requirement, **not a measured coverage percentage**. A linked case may still be failing or not executed; consult [execution-baseline.md](execution-baseline.md).

| Requirement | Domain | Unit cases | Integration cases | Cases with executable links |
|---|---|---:|---:|---:|
| REQ-AUTH | Authentication and office permissions | 4 | 6 | 10 |
| REQ-PEOPLE | People, payroll and employee documents | 4 | 5 | 5 |
| REQ-CLIENT | Client master, KYC and account profile | 3 | 8 | 6 |
| REQ-VENDOR | Vendor master and payable profile | 8 | 12 | 15 |
| REQ-PRODUCT | Products and price calculator | 3 | 3 | 0 |
| REQ-LEAD | Leads and sales follow-up | 3 | 3 | 0 |
| REQ-PROJECT | Project master and product lanes | 3 | 5 | 2 |
| REQ-WORKFLOW | Execution, approvals, gates and financial closure | 3 | 7 | 7 |
| REQ-QC | Quality inspection and sampling | 3 | 3 | 0 |
| REQ-PACK | Packaging lists, verification and print labels | 3 | 5 | 4 |
| REQ-SHIP | Shipments, dispatch and delivery | 4 | 10 | 9 |
| REQ-SALES | Proformas, cash invoices and tax invoices | 3 | 35 | 35 |
| REQ-PURCHASE | Purchase documents and vendor settlement | 3 | 9 | 7 |
| REQ-CASH | Cashflow, allocations and account transfers | 3 | 8 | 5 |
| REQ-RECUR | Recurring cashflow planning and posting | 3 | 3 | 0 |
| REQ-STATEMENT | Party statements and reconciliation | 4 | 7 | 5 |
| REQ-ASSET | Asset register, allocation and depreciation | 3 | 3 | 3 |
| REQ-ORG | Organisation, addresses and banking identity | 3 | 3 | 0 |
| REQ-SERVICE | Office services and linked spending | 3 | 3 | 0 |
| REQ-TASK | Tasks and coordinator queues | 3 | 3 | 0 |
| REQ-NOTE | Private notes | 3 | 3 | 3 |
| REQ-FEEDBACK | Feedback scores and closure | 3 | 3 | 0 |
| REQ-BRIEF | Office briefings, alerts and watchers | 3 | 9 | 6 |
| REQ-SEARCH | Global search and navigation | 3 | 3 | 0 |
| REQ-SETTINGS | Settings, workflow policy and master options | 3 | 3 | 0 |
| REQ-CPORTAL | Client portal authentication and data isolation | 3 | 3 | 2 |
| REQ-VPORTAL | Vendor portal and secure share links | 3 | 3 | 1 |
| REQ-DASH | Overview and module dashboard reporting | 4 | 6 | 6 |
| REQ-AUDIT | Audit trail and sensitive operations | 3 | 4 | 2 |
| REQ-FILES | Shared file policy and storage lifecycle | 3 | 4 | 4 |
| REQ-UI | Shared UI, printing and accessible interactions | 4 | 9 | 5 |
| REQ-TAX | GST: GSTIN checksum, HSN/SAC master and e-invoice (IRN/QR) | 1 | 13 | 14 |
