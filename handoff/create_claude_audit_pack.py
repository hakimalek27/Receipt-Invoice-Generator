import datetime as dt
import hashlib
import json
import re
import shutil
import sys
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

try:
    from PIL import Image
except Exception:
    Image = None

try:
    import pdfplumber
except Exception:
    pdfplumber = None


ROOT = Path(r"c:\Projek Coding\Receipt Invoice Generator")
BUNDLE = ROOT / "handoff" / "claude-audit-pack-2026-05-17"
REF_EXCEL = BUNDLE / "references" / "excel"
REF_PDF = BUNDLE / "references" / "pdf"
REF_IMG = BUNDLE / "references" / "images" / "persada"
ANALYSIS = BUNDLE / "analysis"

REFERENCE_SPECS = [
    ("Q-3118.xlsx", r"C:\Users\hakim\Downloads\Q-3118.xlsx", REF_EXCEL, "excel", "Nas Ceria Services", "NAS Ceria Vertex42 quote/invoice", "8 sheets; NAS Ceria/Vertex42 style; Arial; quotation/invoice examples; item columns No, Item/Description, Qty, Rate, Amount."),
    ("Projek Masjid Muaz Bin jabal (2).xlsx", r"C:\Users\hakim\Downloads\Projek Masjid Muaz Bin jabal (2).xlsx", REF_EXCEL, "excel", "Nas Ceria Services", "NAS Ceria quote/invoice with project sheets", "20 sheets; visible and hidden sheets; quotes/invoices mixed with inventory/costing sheets for Masjid Muadz/Muaz project."),
    ("INV-ABG HANIF BANNER.xlsx", r"C:\Users\hakim\Downloads\INV-ABG HANIF BANNER.xlsx", REF_EXCEL, "excel", "Wehdah Solution", "Wehdah invoice/quotation/artwork", "18 sheets; Wehdah Lao UI/Rockwell template family; many artwork pages; A4 invoice plus artwork confirmation layouts."),
    ("INV-ABG HANIF BANNER (1).xlsx", r"C:\Users\hakim\Downloads\INV-ABG HANIF BANNER (1).xlsx", REF_EXCEL, "excel", "Wehdah Solution", "Wehdah invoice/quotation/artwork", "15 sheets; same Wehdah template family; ARTWORK sheet with invoice and artwork continuation pages."),
    ("INV-latest.xlsx", r"C:\Users\hakim\Downloads\INV-latest.xlsx", REF_EXCEL, "excel", "Wehdah Solution", "Wehdah invoice/quotation/artwork latest", "16 sheets; Wehdah templates; includes recent artwork examples I-241207 and I-250110."),
    ("sham.xlsx", r"C:\Users\hakim\Downloads\sham.xlsx", REF_EXCEL, "excel", "Wehdah Solution", "Wehdah artwork invoice examples", "2 artwork sheets; Wehdah invoice layout with dense embedded artwork images."),
    ("QUOTATION MUBZ (1).xlsx", r"C:\Users\hakim\Downloads\QUOTATION MUBZ (1).xlsx", REF_EXCEL, "excel", "Wehdah Solution", "Wehdah quotation/invoice plus blank billing templates", "16 sheets; active Wehdah MUBZ quotation/invoice examples plus A To Z blank templates for DO, cash bill, PO, notes, receipt, voucher."),
    ("QUOTATION MUBZ (2).xlsx", r"C:\Users\hakim\Downloads\QUOTATION MUBZ (2).xlsx", REF_EXCEL, "excel", "Wehdah Solution", "Wehdah quotation/invoice plus blank billing templates duplicate/reference", "16 sheets; parsed structure matches QUOTATION MUBZ (1); keep as supplied reference for comparison."),
    ("INV-SIGNAGE dec.pdf", r"C:\Users\hakim\Downloads\INV-SIGNAGE dec.pdf", REF_PDF, "pdf", "Wehdah Solution", "Wehdah issued invoice PDF with artwork page", "2 pages; page 1 invoice, page 2 artwork page; page size 1020x1320 pt; exact PDF output target for artwork attachment workflow."),
    ("PERSADA GEMILANG GLOBAL.xlsx", r"C:\Users\hakim\Downloads\Persada Gemilang Global\PERSADA GEMILANG GLOBAL.xlsx", REF_EXCEL, "excel", "Persada Gemilang Global", "Persada workbook with mixed template sheets", "13 sheets; canonical Persada billing sheet is INV 1; other sheets contain A To Z, Virtue, or Wehdah data and must not be treated as Persada canonical."),
    ("COP PERSADA GEMILANG GLOBAL.png", r"C:\Users\hakim\Downloads\Persada Gemilang Global\COP PERSADA GEMILANG GLOBAL.png", REF_IMG, "image", "Persada Gemilang Global", "Persada stamp/chop", "PNG transparent stamp/chop; 294x296; blue company stamp with registration number."),
    ("Letterhead Persada Gemilang Global.jpg", r"C:\Users\hakim\Downloads\Persada Gemilang Global\Letterhead Persada Gemilang Global.jpg", REF_IMG, "image", "Persada Gemilang Global", "Persada A4 letterhead", "A4 letterhead background; 4963x7019; header logo/contact block, gradient bar, footer steps."),
    ("LOGO PERSADA GEMILANG GLOBAL.jpg", r"C:\Users\hakim\Downloads\Persada Gemilang Global\LOGO PERSADA GEMILANG GLOBAL.jpg", REF_IMG, "image", "Persada Gemilang Global", "Persada logo", "JPG logo; 1048x408; navy/blue and green mark with uppercase wordmark."),
]

NS = {
    "main": "http://schemas.openxmlformats.org/spreadsheetml/2006/main",
    "rel": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
}


def q(tag):
    return "{%s}%s" % (NS["main"], tag)


def sha256(path):
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def file_iso(ts):
    return dt.datetime.fromtimestamp(ts).astimezone().isoformat(timespec="seconds")


def clean(value):
    return re.sub(r"\s+", " ", str(value)).strip()


def short(value, limit=160):
    value = clean(value)
    return value if len(value) <= limit else value[: limit - 3] + "..."


def read_xml(zf, name):
    try:
        return ET.fromstring(zf.read(name))
    except KeyError:
        return None


def xml_text(node):
    return "".join(node.itertext()) if node is not None else ""


def shared_strings(zf):
    root = read_xml(zf, "xl/sharedStrings.xml")
    if root is None:
        return []
    return [xml_text(si) for si in root.findall(q("si"))]


def rels(zf, name):
    root = read_xml(zf, name)
    out = {}
    if root is not None:
        for rel in root:
            out[rel.attrib.get("Id")] = rel.attrib
    return out


def target_path(base, target):
    if target.startswith("/"):
        return target.lstrip("/")
    return str(Path(base, target).as_posix())


def cell_value(cell, shared):
    kind = cell.attrib.get("t")
    val = cell.find(q("v"))
    if kind == "inlineStr":
        return xml_text(cell.find(q("is")))
    if val is None:
        return ""
    raw = val.text or ""
    if kind == "s":
        try:
            return shared[int(raw)]
        except Exception:
            return raw
    return raw


def split_ref(ref):
    match = re.match(r"([A-Z]+)(\d+)", ref or "")
    if not match:
        return None
    col = 0
    for ch in match.group(1):
        col = col * 26 + ord(ch) - 64
    return col, int(match.group(2))


def a1(col, row):
    label = ""
    while col:
        col, rem = divmod(col - 1, 26)
        label = chr(65 + rem) + label
    return label + str(row)


def excel_date(value):
    try:
        number = float(value)
        if 20000 < number < 60000:
            return (dt.datetime(1899, 12, 30) + dt.timedelta(days=number)).strftime("%Y-%m-%d")
    except Exception:
        pass
    return None


def neighbor(cells, ref):
    pos = split_ref(ref)
    if not pos:
        return ""
    col, row = pos
    for dc, dr in ((1, 0), (0, 1), (2, 0), (0, 2)):
        value = clean(cells.get(a1(col + dc, row + dr), ""))
        if value:
            return value
    return ""


def summarize_cells(cells):
    values = [(ref, clean(value)) for ref, value in cells.items() if clean(value)]
    top = [(ref, value) for ref, value in values if split_ref(ref) and split_ref(ref)[1] <= 8]
    company = ""
    for pattern in ("WEHDAH", "NAS CERIA", "PERSADA", "VIRTUE", "MUBZ", "A To Z"):
        hit = next((value for _, value in top + values[:60] if pattern.lower() in value.lower()), "")
        if hit:
            company = hit
            break
    if not company and top:
        company = top[0][1]

    doc_candidates = []
    doc_re = re.compile(r"(?i)^(quotation|invoice|official receipt|receipt|delivery order|cash bill|purchase order|credit note|debit note|payment voucher|tax invoice|proforma invoice)$")
    for ref, value in values:
        if doc_re.fullmatch(value):
            pos = split_ref(ref)
            doc_candidates.append((pos[1] if pos else 999, ref, value))
    doc_type = sorted(doc_candidates, key=lambda item: (item[0], item[1]))[-1][2] if doc_candidates else ""

    number = ""
    date_value = ""
    customer = ""
    total = ""
    payment_signals = []
    header_signals = []
    for ref, value in values:
        low = value.lower()
        if not number and (low in {"no.:", "no", "quotation #", "quote #", "invoice #"} or "quotation #" in low or "quote #" in low):
            number = neighbor(cells, ref)
        if not date_value and low in {"date", "date:"}:
            raw = neighbor(cells, ref)
            date_value = excel_date(raw) or raw
        if not customer and (low in {"customer", "bill to:", "to:", "received from:", "pay to:"} or low.startswith("customer details")):
            customer = neighbor(cells, ref)
        if not total and re.search(r"(grand total|total quote|total invoice|total amount)", low):
            total = neighbor(cells, ref)
        if re.search(r"(grand total|subtotal|sub total|total quote|total invoice|bank details|beneficiary|account no|payment)", low):
            payment_signals.append({"cell": ref, "text": short(value)})
        if re.search(r"\b(item|description|qty|unit|rate|price|discount|amount|total price|remark)\b", value, re.I):
            header_signals.append({"cell": ref, "text": short(value)})

    if not number:
        number_like = [value for _, value in values if re.search(r"\b(INV|I|Q|QT|OF|DO|DN|CN|PV|RCPT)[-_ ]?\d", value, re.I)]
        number = number_like[0] if number_like else ""
    if not date_value:
        for _, value in values:
            converted = excel_date(value)
            if converted:
                date_value = converted
                break

    item_like_rows = 0
    for ref, value in values:
        pos = split_ref(ref)
        if pos and re.fullmatch(r"\d{1,3}", value) and 10 <= pos[1] <= 90:
            item_like_rows += 1

    return {
        "detected_company_or_header": short(company, 220),
        "detected_document_type": short(doc_type, 80),
        "detected_document_number": short(number, 100),
        "detected_date": short(date_value, 100),
        "detected_customer": short(customer, 220),
        "detected_total": short(total, 100),
        "item_like_row_count": item_like_rows,
        "table_header_signals": header_signals[:20],
        "payment_total_signals": payment_signals[:16],
        "first_non_empty_cells": [{"cell": ref, "text": short(value, 140)} for ref, value in values[:30]],
    }


def summarize_xlsx(path):
    with zipfile.ZipFile(path) as zf:
        names = zf.namelist()
        shared = shared_strings(zf)
        media = []
        for name in [name for name in names if name.startswith("xl/media/")]:
            item = {"path": name, "file_name": Path(name).name}
            if Image is not None:
                try:
                    with Image.open(zf.open(name)) as img:
                        item.update({"width": img.width, "height": img.height, "mode": img.mode})
                except Exception as exc:
                    item["unreadable_reason"] = type(exc).__name__
            media.append(item)

        workbook = read_xml(zf, "xl/workbook.xml")
        if workbook is None:
            return {"file_name": path.name, "error": "xl/workbook.xml missing"}

        workbook_rels = rels(zf, "xl/_rels/workbook.xml.rels")
        sheets_node = workbook.find(q("sheets"))
        sheets = []
        for sheet in sheets_node.findall(q("sheet")) if sheets_node is not None else []:
            name = sheet.attrib.get("name")
            state = sheet.attrib.get("state", "visible")
            rid = sheet.attrib.get("{%s}id" % NS["rel"])
            ws_path = target_path("xl", workbook_rels.get(rid, {}).get("Target", ""))
            root = read_xml(zf, ws_path)
            if root is None:
                sheets.append({"name": name, "state": state, "path": ws_path, "error": "worksheet missing"})
                continue
            dim = root.find(q("dimension"))
            page_setup = root.find(q("pageSetup"))
            page_margins = root.find(q("pageMargins"))
            merge_cells = root.find(q("mergeCells"))
            cells = {}
            sheet_data = root.find(q("sheetData"))
            if sheet_data is not None:
                for row in sheet_data.findall(q("row")):
                    for cell in row.findall(q("c")):
                        value = cell_value(cell, shared)
                        if clean(value):
                            cells[cell.attrib.get("r")] = value
            sheets.append(
                {
                    "name": name,
                    "state": state,
                    "path": ws_path,
                    "dimension": dim.attrib.get("ref") if dim is not None else "",
                    "non_empty_cell_count": len(cells),
                    "merge_cell_count": len(list(merge_cells)) if merge_cells is not None else 0,
                    "page_setup": page_setup.attrib if page_setup is not None else {},
                    "page_margins": page_margins.attrib if page_margins is not None else {},
                    **summarize_cells(cells),
                }
            )
        return {
            "file_name": path.name,
            "relative_path": str(path.relative_to(BUNDLE)).replace("\\", "/"),
            "sheet_count": len(sheets),
            "media_count": len(media),
            "media": media[:80],
            "sheets": sheets,
        }


def summarize_pdf(path):
    result = {"file_name": path.name, "relative_path": str(path.relative_to(BUNDLE)).replace("\\", "/")}
    if pdfplumber is None:
        result["warning"] = "pdfplumber unavailable; page text not extracted"
        return result
    pages = []
    with pdfplumber.open(path) as pdf:
        for idx, page in enumerate(pdf.pages, 1):
            pages.append(
                {
                    "page": idx,
                    "width_points": round(page.width, 2),
                    "height_points": round(page.height, 2),
                    "text_excerpt": short(page.extract_text() or "", 1600),
                }
            )
    result["page_count"] = len(pages)
    result["pages"] = pages
    return result


def summarize_image(path):
    result = {"file_name": path.name, "relative_path": str(path.relative_to(BUNDLE)).replace("\\", "/")}
    if Image is None:
        result["warning"] = "PIL unavailable; image dimensions not extracted"
        return result
    with Image.open(path) as img:
        result.update({"width": img.width, "height": img.height, "mode": img.mode})
    return result


def main():
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    for directory in (REF_EXCEL, REF_PDF, REF_IMG, ANALYSIS):
        directory.mkdir(parents=True, exist_ok=True)

    generated_at = dt.datetime.now().astimezone().isoformat(timespec="seconds")
    manifest_files = []
    for _, source, dest_dir, category, company, family, note in REFERENCE_SPECS:
        src = Path(source)
        if not src.exists():
            raise FileNotFoundError(f"Missing required reference file: {src}")
        dest = dest_dir / src.name
        shutil.copy2(src, dest)
        src_hash = sha256(src)
        dest_hash = sha256(dest)
        if src_hash != dest_hash:
            raise RuntimeError(f"Checksum mismatch after copy: {src} -> {dest}")
        stat = src.stat()
        manifest_files.append(
            {
                "file_name": src.name,
                "category": category,
                "company": company,
                "document_family": family,
                "source_path": str(src),
                "copied_path": str(dest),
                "copied_relative_path": str(dest.relative_to(BUNDLE)).replace("\\", "/"),
                "size_bytes": stat.st_size,
                "source_last_modified": file_iso(stat.st_mtime),
                "copied_last_modified": file_iso(dest.stat().st_mtime),
                "sha256": src_hash,
                "audit_note": note,
            }
        )

    workbook_summaries = []
    pdf_summaries = []
    image_summaries = []
    for entry in manifest_files:
        copied = Path(entry["copied_path"])
        if entry["category"] == "excel":
            workbook_summaries.append(summarize_xlsx(copied))
        elif entry["category"] == "pdf":
            pdf_summaries.append(summarize_pdf(copied))
        elif entry["category"] == "image":
            image_summaries.append(summarize_image(copied))

    manifest = {
        "generated_at": generated_at,
        "bundle_root": str(BUNDLE),
        "source_policy": "Only explicitly listed reference files were copied. The whole Persada folder was intentionally not copied.",
        "file_count": len(manifest_files),
        "files": manifest_files,
    }

    workbook_audit = {
        "generated_at": generated_at,
        "purpose": "Read-only template audit for Claude and downstream coding agents. Historical Excel records are not production data.",
        "workbooks": workbook_summaries,
        "pdfs": pdf_summaries,
        "images": image_summaries,
        "layout_patterns": [
            {
                "name": "NAS Ceria Vertex42 family",
                "companies": ["Nas Ceria Services"],
                "source_files": ["Q-3118.xlsx", "Projek Masjid Muaz Bin jabal (2).xlsx"],
                "notes": [
                    "Mostly Arial 9/10/11, thin table borders, pale header fills, many merged description/footer cells.",
                    "Quotation/invoice layout generally uses No, Item/Description, Qty, Rate (RM), Amount (RM).",
                    "Portrait A4-like print setup with about 0.5 inch margins.",
                    "Some sheets are hidden, old, or costing/inventory sheets and must not become production records.",
                ],
            },
            {
                "name": "Wehdah Lao UI/Rockwell family",
                "companies": ["Wehdah Solution"],
                "source_files": ["INV-ABG HANIF BANNER.xlsx", "INV-ABG HANIF BANNER (1).xlsx", "INV-latest.xlsx", "sham.xlsx", "QUOTATION MUBZ (1).xlsx", "QUOTATION MUBZ (2).xlsx", "INV-SIGNAGE dec.pdf"],
                "notes": [
                    "Mostly Lao UI 13/14/16/20 with Rockwell/Rockwell Extra Bold title/logo areas.",
                    "Dark blue fill around FF002060, grey fills, medium outer borders, thin inner table borders.",
                    "Common item table columns: Item, Description, Qty, Unit, Unit Price, Discount, Total Price.",
                    "Artwork sheets demonstrate final page appended after invoice with artwork thumbnails, confirmation text, and company sign/chop area.",
                ],
            },
            {
                "name": "Persada official assets",
                "companies": ["Persada Gemilang Global"],
                "source_files": ["PERSADA GEMILANG GLOBAL.xlsx", "LOGO PERSADA GEMILANG GLOBAL.jpg", "COP PERSADA GEMILANG GLOBAL.png", "Letterhead Persada Gemilang Global.jpg"],
                "notes": [
                    "Use INV 1 as canonical Persada invoice source; other workbook sheets contain mixed A To Z, Virtue, or Wehdah data.",
                    "Logo JPG is white-background 1048x408; stamp PNG is transparent 294x296; letterhead is A4 ratio 4963x7019.",
                    "Letterhead address wording differs slightly from workbook and must be confirmed before production.",
                ],
            },
            {
                "name": "Generic A To Z billing template sheets",
                "companies": ["Template only"],
                "source_files": ["A To Z sheets inside multiple workbooks"],
                "notes": [
                    "A To Z Account sheets are reusable design references for DO, cash bill, purchase order, credit note, debit note, official receipt, and payment voucher.",
                    "They are not a real company profile for this system unless user later confirms otherwise.",
                ],
            },
        ],
        "critical_cautions": [
            "Do not import old Excel rows as live production records. They are design references only.",
            "Do not infer current numbering from old messy Excel files. Start clean configurable sequences per company and document type.",
            "Do not treat mixed A To Z/Virtue/Wehdah sheets as Persada canonical templates.",
            "Do not let Telegram or AI issue a document without explicit human confirmation.",
        ],
    }

    reference_files = [
        {
            "file_name": entry["file_name"],
            "category": entry["category"],
            "company": entry["company"],
            "document_family": entry["document_family"],
            "relative_path": entry["copied_relative_path"],
            "sha256": entry["sha256"],
            "audit_note": entry["audit_note"],
        }
        for entry in manifest_files
    ]

    plan = build_plan(generated_at, reference_files)

    (BUNDLE / "reference-manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (ANALYSIS / "workbook-audit-summary.json").write_text(json.dumps(workbook_audit, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (BUNDLE / "receipt-invoice-generator-audit-plan.json").write_text(json.dumps(plan, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (BUNDLE / "README_FOR_CLAUDE.md").write_text(readme_text(), encoding="utf-8")
    (ANALYSIS / "template-design-notes.md").write_text(template_design_notes(), encoding="utf-8")
    (ANALYSIS / "open-questions.md").write_text(open_questions(), encoding="utf-8")

    print(f"Bundle created: {BUNDLE}")
    print(f"Reference files copied: {len(manifest_files)}")
    print(f"Workbook summaries: {len(workbook_summaries)}")
    print(f"PDF summaries: {len(pdf_summaries)}")
    print(f"Image summaries: {len(image_summaries)}")


def build_plan(generated_at, reference_files):
    return {
        "meta": {
            "title": "Receipt Invoice Generator Claude Audit Pack",
            "created_at": generated_at,
            "created_for": "Claude audit and improvement pass before implementation",
            "bundle_root": str(BUNDLE),
            "language_context": ["Malay", "English"],
            "status": "handoff_ready",
            "important_note": "This pack is for audit and implementation planning. It intentionally does not create the production app yet.",
        },
        "audit_instruction_for_claude": {
            "primary_task": "Audit this plan deeply, identify gaps, risks, missing requirements, and propose improvements without changing locked user decisions unless justified.",
            "must_read_first": ["receipt-invoice-generator-audit-plan.json", "analysis/workbook-audit-summary.json", "reference-manifest.json", "README_FOR_CLAUDE.md"],
            "audit_angles": [
                "Check whether document scope is sufficient for Malaysia SME and global use.",
                "Check whether data model supports invoice, quotation, DO, receipt, payment, refund, credit note, debit note, purchase order, cash bill, and voucher workflows.",
                "Check whether numbering policy is collision-safe and strictly per company and per document type.",
                "Check whether PDF renderer approach can match Excel-style templates while keeping maintainable HTML/CSS templates.",
                "Check whether Telegram plus DeepSeek workflow is safe and cannot bypass user confirmation.",
                "Check whether e-Invoice metadata is enough for later MyInvois integration.",
                "Check whether Tencent Ubuntu deployment is secure, maintainable, backed up, and observable.",
                "Check whether template audit separated canonical company formats from mixed sample sheets correctly.",
            ],
            "expected_output_from_claude": [
                "A revised plan with clear improvements and no ambiguity for implementers.",
                "A risk register with severity and mitigation.",
                "A missing-info checklist grouped by company and workflow.",
                "Recommended implementation phase ordering and acceptance gates.",
                "Any proposed schema or API changes that reduce future rework.",
            ],
            "do_not_do": [
                "Do not treat historical Excel records as production imports.",
                "Do not merge company numbering or template identity.",
                "Do not redesign all document templates unless user later asks.",
                "Do not require full MyInvois submission in v1.",
            ],
        },
        "locked_user_decisions": {
            "historical_excel_policy": "Template reference only, not imported as production records.",
            "document_design_policy": "Match plus kemas: preserve original design identity while improving spacing, typos, table consistency, and polish.",
            "company_isolation": "Every company has separate profile, templates, records, numbering, and sequences.",
            "numbering_issue_rule": "Official numbers are generated only when a document is issued, not while it is a draft.",
            "einvoice_v1_scope": "Metadata-ready only. MyInvois submission/signing/cancel/reject is not part of v1.",
            "no_cross_company_numbering": "Wehdah, Persada, Virtue Damsel, and Nas Ceria numbering must never share one sequence.",
            "workspace_state": "Current workspace is empty, so build a new system rather than refactor an existing app.",
        },
        "project_goal": {
            "summary": "Build a premium multi-company receipt, invoice, delivery order, quotation, and related business document generator to replace messy Excel workflows.",
            "business_problem": [
                "Excel files are saved across different PCs, causing scattered records and inconsistent document numbers.",
                "Invoice, quotation, receipt, DO, and artwork references are hard to search and verify.",
                "Multiple companies require independent formats and independent numbering.",
            ],
            "success_definition": [
                "User can choose company, document type, customer, items, payment details, optional artwork, then generate A4 PDF or 60mm thermal receipt.",
                "All issued documents are searchable, immutable, and safely numbered per company/document type/year.",
                "Company details, logo, stamp, signature, bank details, footer terms, templates, and numbering rules are editable.",
                "Telegram and API flows create drafts and require confirmation before issue.",
            ],
        },
        "target_server": {
            "provider": "Tencent Cloud",
            "ip": "43.133.34.55",
            "os": "Ubuntu",
            "recommended_runtime": "Docker Compose on Ubuntu with Nginx reverse proxy, Certbot SSL, PostgreSQL, Redis, PHP-FPM, Laravel Horizon, and queue workers.",
            "ports": ["22 SSH restricted", "80 HTTP redirect to HTTPS", "443 HTTPS"],
            "production_requirements": ["SSL certificate", "firewall", "daily backups", "queue supervisor", "scheduler", "log rotation", "restore test"],
        },
        "current_workspace_state": {
            "path": str(ROOT),
            "observed_state": "Workspace was empty before creating this handoff bundle.",
            "handoff_created": str(BUNDLE),
            "source_code_status": "No app source files yet. The implementation phase should scaffold a new project.",
        },
        "reference_files": reference_files,
        "template_audit_summary": {
            "summary_file": "analysis/workbook-audit-summary.json",
            "nas_ceria": {
                "canonical_sources": ["Q-3118.xlsx", "Projek Masjid Muaz Bin jabal (2).xlsx"],
                "style": "Vertex42-derived quote/invoice style, Arial fonts, pale headers, thin borders, 0.5 inch margins.",
                "important_columns": ["No", "Item/Description", "Qty", "Rate (RM)", "Amount (RM)"],
                "caution": "Contains old sheets, hidden sheets, inventory/costing sheets, and messy numbering. Use as design reference only.",
            },
            "wehdah": {
                "canonical_sources": ["INV-ABG HANIF BANNER.xlsx", "INV-ABG HANIF BANNER (1).xlsx", "INV-latest.xlsx", "sham.xlsx", "QUOTATION MUBZ (1).xlsx", "QUOTATION MUBZ (2).xlsx", "INV-SIGNAGE dec.pdf"],
                "style": "Lao UI/Rockwell invoice/quotation family with dark blue title fills, structured item table, bank line, terms, signature/chop area.",
                "important_columns": ["Item", "Description", "Qty", "Unit", "Unit Price", "Discount", "Total Price"],
                "artwork_pattern": "A4 invoice page followed by artwork page with thumbnails/captions and confirmation/sign-chop block.",
            },
            "persada": {
                "canonical_sources": ["PERSADA GEMILANG GLOBAL.xlsx INV 1", "Persada logo", "Persada stamp", "Persada letterhead"],
                "style": "Navy/green brand, logo/contact header, Persada stamp, optional full A4 letterhead background.",
                "caution": "Only INV 1 is canonical in workbook. Other sheets contain mixed A To Z, Virtue, and Wehdah data.",
            },
            "virtue_damsel": {
                "canonical_sources": ["Mixed sheet in PERSADA GEMILANG GLOBAL.xlsx"],
                "status": "Partial only. Needs user-confirmed logo, stamp, bank, address, TIN/SST, and final template rules.",
            },
            "generic_a_to_z": {
                "status": "Template reference only. Not a company profile by default.",
                "usable_for": ["Delivery Order", "Cash Bill", "Purchase Order", "Credit Note", "Debit Note", "Official Receipt", "Payment Voucher"],
            },
        },
        "companies": [
            {
                "name": "Wehdah Solution",
                "code": "WS",
                "registration_no": "202103190949 (PG0514579-H)",
                "address": ["Wisma UOA II, Unit/Unite No: 15-13A", "UOA Business Centre, Jalan Pinang", "50450 Kuala Lumpur, Malaysia"],
                "phone": "+6017-3123415",
                "email": "wehdahsolution@gmail.com",
                "bank_accounts_observed": ["Hong Leong Islamic 18701038380", "Bank Islam 12113010769313"],
                "template_family": "Wehdah Lao UI/Rockwell",
                "supported_document_types_v1": ["quotation", "invoice", "receipt", "delivery_order", "cash_bill", "credit_note", "debit_note", "purchase_order", "payment_voucher", "proforma_invoice"],
                "special_features": ["artwork attachment pages for printing/design jobs"],
                "missing_info": ["TIN", "SST/tax status", "official logo source if any", "signature asset/name/title", "final canonical address spelling Unit vs Unite"],
            },
            {
                "name": "Persada Gemilang Global",
                "code": "PGG",
                "registration_no": "202503350239 (AS0507799-U)",
                "address_observed_workbook": ["D-11-5,PPR Jalan Sri Sentosa", "Jalan Taman Seri Sentosa", "58000 Kuala Lumpur"],
                "address_observed_letterhead": ["D-11-5, PPR Jalan Sri Sentosa", "Jalan Taman Seri Sentosa Taman Seri Sentosa", "58000 KL"],
                "phone": "+6010-353 1955",
                "email": "persadagemilangmy@gmail.com",
                "website_observed": "www.persadagemilang.my",
                "bank_accounts_observed": ["Bank Rakyat 110 258 1847"],
                "template_family": "Persada navy/green brand with letterhead and stamp",
                "supported_document_types_v1": ["quotation", "invoice", "receipt", "delivery_order", "cash_bill", "credit_note", "debit_note", "purchase_order", "payment_voucher", "proforma_invoice"],
                "missing_info": ["Canonical address wording", "Bank account holder name", "TIN", "SST/tax status", "signer name/title", "whether website appears on billing PDFs"],
            },
            {
                "name": "Nas Ceria Services",
                "code": "NCS",
                "registration_no": "003035718-X",
                "address": ["14-1, 1st Floor, Jalan Wangsa Budi 1", "Taman Wangsa Melawati", "53300 Kuala Lumpur"],
                "phone": "019-3733467",
                "email_observed": "Toncet30@gmail.com",
                "bank_accounts_observed": ["Am Bank 8881039261301"],
                "template_family": "NAS Ceria Vertex42/Arial",
                "supported_document_types_v1": ["quotation", "invoice", "receipt", "delivery_order", "cash_bill", "credit_note", "debit_note", "purchase_order", "payment_voucher", "proforma_invoice"],
                "missing_info": ["TIN", "SST/tax status", "official logo/stamp/signature assets", "canonical email case/spelling", "final bank account holder name"],
            },
            {
                "name": "Virtue Damsel Solution",
                "code": "VDS",
                "registration_no_observed": "002976929-K",
                "address_observed": ["No 30, RD 3, Taman Ramal Desa", "43000 Kajang Selangor"],
                "phone_observed": "03 89125200",
                "bank_accounts_observed_unverified": ["Maybank 5620 2164 4859"],
                "template_family": "Partial mixed workbook reference only",
                "supported_document_types_v1": ["quotation", "invoice", "receipt", "delivery_order", "cash_bill", "credit_note", "debit_note", "purchase_order", "payment_voucher", "proforma_invoice"],
                "missing_info": ["Confirm company is active/in-scope", "logo", "stamp", "signature", "email", "website", "TIN", "SST/tax status", "canonical bank details", "canonical template source"],
            },
        ],
        "document_types": [
            {"key": "quotation", "label": "Quotation", "default_code": "Q", "purpose": "Quote before sale/work confirmation", "can_convert_to": ["invoice", "delivery_order"], "amount_document": True},
            {"key": "invoice", "label": "Invoice", "default_code": "INV", "purpose": "Amount payable by customer", "can_convert_to": ["receipt", "delivery_order", "credit_note", "debit_note"], "amount_document": True, "einvoice_metadata_relevant": True},
            {"key": "receipt", "label": "Official Receipt", "default_code": "RCPT", "purpose": "Payment received proof", "amount_document": True},
            {"key": "delivery_order", "label": "Delivery Order", "default_code": "DO", "purpose": "Delivery/received goods confirmation", "can_convert_to": ["invoice"], "amount_document": False},
            {"key": "cash_bill", "label": "Cash Bill", "default_code": "CB", "purpose": "Immediate cash sale record", "amount_document": True},
            {"key": "credit_note", "label": "Credit Note", "default_code": "CN", "purpose": "Reduce amount owed/refund adjustment", "amount_document": True, "einvoice_metadata_relevant": True},
            {"key": "debit_note", "label": "Debit Note", "default_code": "DN", "purpose": "Increase amount owed/charge adjustment", "amount_document": True, "einvoice_metadata_relevant": True},
            {"key": "purchase_order", "label": "Purchase Order", "default_code": "PO", "purpose": "Order to supplier", "amount_document": True},
            {"key": "payment_voucher", "label": "Payment Voucher", "default_code": "PV", "purpose": "Outgoing payment approval/record", "amount_document": True},
            {"key": "proforma_invoice", "label": "Proforma Invoice", "default_code": "PFI", "purpose": "Preliminary invoice before final invoice", "amount_document": True},
        ],
        "numbering_policy": {
            "scope": "company_id + document_type + year + optional branch/project/reset_policy",
            "default_pattern": "{COMPANY_CODE}-{DOC_CODE}-{YY}{SEQ_3}",
            "example_2026": ["WS-INV-26001", "WS-Q-26001", "PGG-INV-26001", "NCS-Q-26001", "VDS-RCPT-26001"],
            "sequence_start_default": 1,
            "sequence_display_default": "YY plus 3 digits, e.g. 26001 for first 2026 document",
            "draft_policy": "Drafts have internal draft_id only. Final number is allocated inside a DB transaction when issued.",
            "collision_control": ["DB unique constraint on company_id, document_type, issue_year, document_number", "Row lock sequence counter during issue", "Idempotency key for API-created documents"],
            "manual_override": "Admin may set starting number before first issue for a company/type/year. Overrides after issue require audit log and no duplicate allowed.",
            "legacy_numbers": "Old Excel numbers are stored only as design examples, not used to calculate next production number unless user explicitly decides later.",
        },
        "document_workflows": {
            "statuses": ["draft", "issued", "sent", "accepted", "converted", "partial_paid", "paid", "void", "cancelled"],
            "draft": ["Editable fields, no official number, preview watermark optional, attachments allowed"],
            "issue": ["Validate required fields, allocate official number transactionally, render immutable PDF, write audit event"],
            "send": ["Email/Telegram/share link optional, audit event recorded"],
            "convert": ["Quotation to invoice/DO, invoice to receipt, invoice adjustment to credit/debit note; preserve source_document_id link"],
            "void_cancel": ["Issued document cannot be deleted; void/cancel requires reason and preserves PDF"],
            "payment": ["Support partial/full payment, method, reference number, paid_at, receipt generation"],
        },
        "pdf_and_printing": {
            "a4_pdf_engine": "HTML/CSS rendered through Playwright/Browsershot Chromium for accurate Excel-like layout and image placement.",
            "template_strategy": "Use structured Blade/React-compatible HTML templates per company and document type, not raw Excel generation. Maintain visual match through CSS tokens.",
            "page_sizes": ["A4 portrait for main documents", "60mm width thermal receipt PDF for receipt/quick print"],
            "immutability": "Store final generated PDF and rendered metadata after issue. Re-render only by revision/void/new document, not by silently changing issued PDF.",
            "visual_qa": ["PDF page size check", "Header/company identity check", "No cross-company logo/template leak", "Long item wrapping", "Artwork page fit and caption check"],
        },
        "artwork_attachments": {
            "supported_for": ["invoice", "quotation", "delivery_order"],
            "primary_use_case": "Wehdah printing/design invoices where artwork/design proof must be appended after the main document.",
            "inputs": ["jpg", "jpeg", "png", "webp", "pdf"],
            "storage": "Preserve original uploaded file and generate normalized preview thumbnails.",
            "layout": "Append one or more A4 pages after main PDF. Use clean grid/table with labels Artwork 1, Artwork 2, etc., confirmation line, and company sign/chop area.",
            "controls": ["optional upload", "order artwork manually", "caption per artwork", "include/exclude per document", "append before/after main document setting"],
        },
        "thermal_receipt": {
            "width": "60mm",
            "format": "Separate compact receipt template, not scaled-down A4.",
            "content": ["Company short name", "receipt number", "date/time", "customer optional", "items/description", "qty", "amount", "subtotal", "discount/tax if any", "grand total", "payment method", "reference number", "thank you/footer", "optional QR"],
            "style": ["monospace/thermal-friendly font", "high contrast", "short line wrapping", "no heavy images unless logo is tiny and optional"],
            "outputs": ["PDF 60mm", "browser print view", "future ESC/POS export optional"],
        },
        "api_requirements": {
            "auth": "Laravel Sanctum for first version; token scopes per company and action.",
            "documentation": "OpenAPI/Swagger generated docs.",
            "core_endpoints": ["companies", "customers", "products/services", "documents", "document lines", "attachments", "payments", "number preview", "issue document", "render PDF", "webhooks"],
            "safety": ["idempotency keys for create/issue", "rate limits", "audit log for all API mutations", "company permission checks on every request"],
            "example_document_create_flow": ["POST draft", "POST line items/attachments", "GET preview PDF", "POST issue with confirmation/idempotency key", "GET final PDF"],
        },
        "telegram_bot": {
            "mode": "Webhook mode integrated with Laravel routes and queues.",
            "permissions": "Whitelist Telegram users and map each user to allowed companies/actions.",
            "commands": ["/new_invoice", "/new_quotation", "/receipt", "/status DOCNO", "/attach", "/preview", "/confirm_issue", "/cancel_draft"],
            "ai_assisted_flow": ["User sends natural language request", "DeepSeek parses into structured draft JSON", "System validates and recalculates totals", "User receives preview and summary", "User must explicitly confirm issue", "System allocates final number and sends PDF"],
            "hard_rule": "Telegram can create drafts and previews, but cannot issue without explicit confirmation from an authorized user.",
        },
        "deepseek_ai": {
            "role": "Optional assistant for parsing/drafting, not source of truth.",
            "planned_model": "deepseek-v4-pro for highest accuracy when available/configured; allow cheaper fallback model for simple extraction.",
            "uses": ["parse Malay/English invoice requests", "extract customer/items/qty/price from text", "draft quotation descriptions", "classify uploaded documents", "translate labels/copy", "flag suspicious total mismatch"],
            "controls": ["strict JSON schema output", "server recalculates totals", "redact sensitive data where possible", "store AI request/response metadata", "manual confirmation required before issue"],
        },
        "einvoice_metadata_ready": {
            "scope_v1": "Store and validate metadata fields needed for future Malaysia MyInvois integration, but do not submit to MyInvois in v1.",
            "supplier_fields": ["TIN", "BRN/registration no", "SST registration no", "MSIC code", "business activity description", "address", "contact", "email"],
            "buyer_fields": ["TIN optional/required by scenario", "BRN/NRIC/passport", "name", "address", "contact", "email"],
            "document_fields": ["classification code", "currency", "exchange rate", "tax type", "tax rate", "tax amount", "exemption reason", "payment mode", "billing period", "reference documents"],
            "future_myinvois_status_fields": ["submission_uid", "long_id", "uuid", "validation_status", "validation_errors", "qr_url", "submitted_at", "cancelled_at", "rejected_at"],
            "documents_relevant_to_future_submission": ["invoice", "credit_note", "debit_note", "refund_note later", "self_billed variants later"],
            "internal_only_documents": ["quotation", "delivery_order", "receipt", "purchase_order", "payment_voucher unless future rules require otherwise"],
        },
        "data_model": {
            "core_tables": ["users", "roles", "permissions", "companies", "company_users", "customers", "products", "services", "documents", "document_lines", "document_attachments", "payments", "sequence_counters", "templates", "template_versions", "audit_logs", "api_tokens", "ai_requests", "telegram_messages", "e_invoice_metadata"],
            "document_key_fields": ["company_id", "document_type", "status", "draft_no", "document_no", "issue_date", "customer_id", "currency", "subtotal", "discount_total", "tax_total", "grand_total", "amount_paid", "balance_due", "source_document_id", "created_by", "issued_by"],
            "line_item_fields": ["description", "long_description", "qty", "unit", "unit_price", "discount_type", "discount_value", "tax_code", "tax_rate", "line_total", "sort_order"],
            "template_fields": ["company_id", "document_type", "name", "version", "is_active", "brand_colors", "logo_position", "font_family", "labels_json", "terms_json", "css_json"],
            "audit_fields": ["actor_id", "company_id", "action", "auditable_type", "auditable_id", "before_json", "after_json", "ip_address", "user_agent", "created_at"],
        },
        "security_audit_backup": {
            "auth": ["2FA optional for admin", "strong password policy", "role-based permissions", "company scoping"],
            "data_safety": ["issued documents immutable", "soft delete drafts only", "no hard delete issued docs", "audit trail for all important changes"],
            "uploads": ["file type validation", "size limits", "virus scan optional/future", "private storage path", "signed temporary download URLs"],
            "backups": ["daily encrypted PostgreSQL dump", "daily storage archive", "offsite backup target recommended", "monthly restore drill"],
            "logs_monitoring": ["Laravel logs", "queue failure logs", "Nginx logs", "backup logs", "disk usage alert", "SSL expiry alert"],
        },
        "deployment_plan": {
            "recommended_stack": ["Laravel 12", "PHP 8.3+", "PostgreSQL", "Redis", "React/Inertia", "Tailwind", "Playwright/Chromium", "Nginx", "Docker Compose"],
            "server_steps": ["Provision Ubuntu server", "Set firewall for 22/80/443", "Install Docker/Compose or native stack", "Configure Nginx and SSL", "Deploy app env", "Run migrations/seeders", "Start queue and scheduler", "Run production smoke tests", "Enable backups"],
            "environments": ["local", "staging optional", "production"],
            "secrets_needed": ["APP_KEY", "DB credentials", "Redis credentials if any", "DeepSeek API key", "Telegram bot token", "Telegram webhook secret", "mail credentials", "backup encryption key"],
        },
        "test_plan": {
            "unit_tests": ["numbering sequence per company/type/year", "concurrent issue collision prevention", "subtotal/discount/tax/grand total calculations", "status transitions", "payment balance calculations"],
            "feature_tests": ["company permission isolation", "draft creation", "issue document", "void/cancel with reason", "convert quotation to invoice/DO", "receipt from payment", "API idempotency", "upload artwork"],
            "pdf_tests": ["A4 page dimensions", "company branding match", "Wehdah artwork appended page", "Persada logo/stamp placement", "NAS table layout", "long description wrapping"],
            "thermal_tests": ["60mm width", "long item text wrapping", "total visible", "payment method/reference", "print CSS sanity"],
            "telegram_ai_tests": ["mock DeepSeek JSON parse", "invalid total rejected", "unauthorized Telegram user rejected", "draft preview before issue", "confirmation required"],
            "production_smoke_tests": ["login", "create draft per company", "preview PDF", "issue one test document per major template family", "download final PDF", "verify queue", "verify backup job/log"],
        },
        "implementation_phases": [
            {"phase": 0, "name": "Audit closure and missing info confirmation", "deliverables": ["Claude audit feedback", "final v1 scope", "confirmed company details/assets"], "acceptance_gate": "No unresolved blocker for v1 scaffold."},
            {"phase": 1, "name": "Scaffold and infrastructure", "deliverables": ["Laravel/Inertia project", "Docker Compose", "PostgreSQL/Redis", "auth/roles", "CI/test baseline"], "acceptance_gate": "App boots locally and tests pass."},
            {"phase": 2, "name": "Company, customers, products, numbering", "deliverables": ["CRUD screens", "sequence counters", "audit log", "company scoping"], "acceptance_gate": "Per-company numbering tests pass under concurrency."},
            {"phase": 3, "name": "Document workflows", "deliverables": ["Draft/issue/void/convert/payment flows", "line items", "attachments"], "acceptance_gate": "Feature tests cover main document lifecycle."},
            {"phase": 4, "name": "PDF and thermal templates", "deliverables": ["Wehdah/NAS/Persada templates", "artwork page", "60mm receipt"], "acceptance_gate": "Visual/PDF checks pass and no company brand leaks."},
            {"phase": 5, "name": "API, Telegram, DeepSeek", "deliverables": ["Sanctum API", "OpenAPI docs", "Telegram bot", "AI parse draft flow"], "acceptance_gate": "AI/Telegram cannot issue without confirmation."},
            {"phase": 6, "name": "Production deployment", "deliverables": ["Tencent Ubuntu deployment", "SSL", "queues", "backups", "smoke evidence"], "acceptance_gate": "Production smoke and backup verification pass."},
        ],
        "known_missing_info": [
            {"company": "Persada Gemilang Global", "missing": ["Canonical address wording", "Bank Rakyat account holder name", "TIN", "SST status", "signer name/title", "whether website should appear on invoices/receipts"]},
            {"company": "Wehdah Solution", "missing": ["TIN", "SST status", "official logo/stamp/signature assets", "canonical address spelling Unit/Unite", "signer name/title"]},
            {"company": "Nas Ceria Services", "missing": ["TIN", "SST status", "logo/stamp/signature assets", "canonical email", "bank account holder name"]},
            {"company": "Virtue Damsel Solution", "missing": ["Confirm active/in-scope", "all company profile fields", "logo/stamp/signature assets", "canonical bank details", "canonical template source"]},
            {"system": "Deployment", "missing": ["Domain name", "SSH username/key", "production email service", "Telegram bot token", "DeepSeek API key", "backup destination"]},
        ],
        "acceptance_criteria": [
            "All explicitly listed reference files exist inside references folders.",
            "reference-manifest.json contains SHA256, size, source path, copied path, company, category, and audit notes for every reference file.",
            "Main JSON can be read by Claude without needing the chat history.",
            "Main JSON includes locked decisions, audit summary, missing info, architecture, API, Telegram, DeepSeek, e-Invoice metadata, deployment, and test plan.",
            "No old Excel records are treated as production records.",
            "No company template, numbering, or records are mixed across companies.",
        ],
    }


def readme_text():
    return """# Claude Audit Pack: Receipt Invoice Generator

This folder is a handoff pack for Claude or another audit agent to review and improve the planned multi-company receipt, invoice, quotation, delivery order, and related document generator.

## Read Order

1. `receipt-invoice-generator-audit-plan.json`
2. `analysis/workbook-audit-summary.json`
3. `reference-manifest.json`
4. `analysis/template-design-notes.md`
5. `analysis/open-questions.md`

## Locked Decisions

- Historical Excel files are template references only, not production imports.
- Document design target is `match + kemas`: preserve the original identity but polish layout and consistency.
- Every company must have separate profile, records, templates, numbering, and sequence counters.
- Official document numbers are allocated only when issuing, never at draft creation.
- e-Invoice v1 is metadata-ready only. Full MyInvois submission is a later phase.
- Do not merge Wehdah, Persada, Virtue Damsel, and Nas Ceria numbering.
- The workspace was empty before this handoff, so implementation should scaffold a new app.

## Audit Request

Please audit for correctness, gaps, security risks, workflow mistakes, Malaysian SME/global document needs, PDF/template feasibility, API safety, Telegram/DeepSeek safety, and e-Invoice future readiness. Suggest improvements, but do not change locked decisions without clear justification.

## Important Caution

The copied Excel files contain mixed old records, hidden sheets, template examples, and sample company data. Treat them as visual and structural references only.
"""


def template_design_notes():
    return """# Template Design Notes

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
"""


def open_questions():
    return """# Open Questions

## Persada Gemilang Global

- What is the canonical address wording: workbook version or letterhead version?
- Is Bank Rakyat `110 258 1847` final, and what is the account holder name?
- Should `www.persadagemilang.my` appear on invoice/receipt PDFs?
- What are the TIN, SST/tax status, signer name, and signer title?
- Is the current JPG logo acceptable, or is a transparent/vector logo available?

## Wehdah Solution

- Confirm official address spelling: `Unit` or `Unite No: 15-13A`.
- Provide official logo, stamp, and signature assets if available.
- Confirm TIN and SST/tax status.
- Confirm bank account holder names for Hong Leong Islamic and Bank Islam.
- Confirm preferred final numbering prefix: `WS-INV-26001`, `INV-26001`, or another company-specific pattern.

## Nas Ceria Services

- Confirm current address, phone, email, and bank account holder.
- Provide logo/stamp/signature assets if required.
- Confirm TIN and SST/tax status.
- Confirm preferred final numbering prefix.

## Virtue Damsel Solution

- Confirm whether this company is active and should be included in v1.
- Provide complete company profile, logo, stamp, signature, bank details, TIN, SST/tax status, and canonical template source.

## System/Deployment

- What domain will point to Tencent server `43.133.34.55`?
- Which SSH user/key should be used for deployment?
- Which email provider should send PDFs?
- What is the Telegram bot token and allowed Telegram user list?
- What DeepSeek API key/model should production use?
- Where should encrypted offsite backups be stored?
"""


if __name__ == "__main__":
    main()
