# Template Design Notes

## Overall Direction

The system should preserve each company's document identity while improving consistency, spacing, typo issues, table alignment, and data discipline. This is not a full redesign and not a blind pixel-perfect clone.

## Wehdah Solution

Use the Lao UI/Rockwell template family as the main reference. Core A4 documents use a strong title/header area, customer/document metadata block, item table, total section, bank line, terms, authorised signature, and company sign/chop. Artwork jobs must support appended A4 artwork pages with a clean grid/table, captions such as Artwork 1, Artwork 2, and a final confirmation/sign-chop area.

Important observed references include `INV-SIGNAGE dec.pdf`, `INV-latest.xlsx`, `INV-ABG HANIF BANNER.xlsx`, and `sham.xlsx`. These show the real invoice plus artwork workflow.

## Nas Ceria Services

Use the Vertex42-derived Arial quote/invoice style from `Q-3118.xlsx` and `Projek Masjid Muaz Bin jabal (2).xlsx`. Keep the familiar table structure: No, Item/Description, Qty, Rate, Amount. Do not use old sheet numbers as production sequence truth.

## Persada Gemilang Global

Use `PERSADA GEMILANG GLOBAL.xlsx` sheet `INV 1` plus the Persada logo, stamp, and letterhead assets. The Persada workbook is mixed with A To Z, Virtue, and Wehdah sheets; only `INV 1` is canonical for Persada billing in this pack. The letterhead can support formal letters/proposals later, while invoice/receipt PDFs should use Persada branding and stamp cleanly.

## Virtue Damsel Solution

Current evidence is partial and mixed. Build company profile capability for Virtue, but mark final logo, stamp, bank, tax, and canonical template as missing until the user supplies or confirms them.

## Generic A To Z Sheets

A To Z Account sheets are useful as generic structure references for DO, cash bill, purchase order, credit note, debit note, official receipt, and payment voucher. They should not be seeded as an actual company unless user confirms.

## Renderer Recommendation

Use HTML/CSS templates rendered with Playwright/Browsershot. This should provide better control than trying to automate Excel itself, while still matching the supplied layouts. Keep CSS tokens per template family: fonts, colors, margins, table borders, row heights, signature blocks, and artwork grid.

## QA Notes

Visual QA must check A4 page size, header identity, company logo/stamp, item table wrapping, total alignment, long customer addresses, artwork image scaling, and no cross-company template leak.
