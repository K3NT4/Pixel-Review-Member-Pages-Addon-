## 2024-05-22 - Contextual Links in Tables
**Learning:** In data tables where every row has an "Edit" or "Delete" link, screen reader users hear repetitive link text without context. Adding `aria-label="Edit [Item Name]"` provides crucial context.
**Action:** Always check table actions for repetitive link text and add dynamic ARIA labels using the row's primary identifier.
