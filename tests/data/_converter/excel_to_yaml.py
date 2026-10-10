"""
Excel → YAML Converter

Converts Sparqla Excel test data sheets into YAML format for the new framework.
QA team continues authoring in Excel → this script converts → CI reads YAML.

Usage:
    python excel_to_yaml.py --input "path/to/retail_data2.xlsx" --output "./data/"
    python excel_to_yaml.py --input "path/to/retail_data2.xlsx" --sheet "Billing"
"""
import argparse
import sys
from pathlib import Path

import yaml
from openpyxl import load_workbook


def excel_sheet_to_yaml(workbook_path: str, sheet_name: str) -> dict:
    """Convert a single Excel sheet to a YAML-compatible dict."""
    wb = load_workbook(workbook_path, read_only=True, data_only=True)

    if sheet_name not in wb.sheetnames:
        print(f"⚠️ Sheet '{sheet_name}' not found in workbook")
        return None

    sheet = wb[sheet_name]
    rows = list(sheet.iter_rows(values_only=True))
    wb.close()

    if len(rows) < 2:
        return None

    # Row 1 = headers
    headers = [str(h).strip() if h else f"col_{i}" for i, h in enumerate(rows[0])]

    # Convert data rows to dicts
    test_cases = []
    for row_idx, row in enumerate(rows[1:], start=2):
        # Skip empty rows (TestCaseId is None)
        if row[0] is None:
            break

        case = {}
        for col_idx, header in enumerate(headers):
            value = row[col_idx] if col_idx < len(row) else None

            # Clean the header for YAML key
            key = header.lower().replace(" ", "_").replace("&", "and")

            # Skip status columns (these are written by automation)
            if key in ("teststatus", "actualstatus"):
                continue

            # Convert None to null
            if value is None:
                continue

            # Convert numeric strings
            if isinstance(value, str) and value.strip() == "":
                continue

            case[key] = value

        if case:
            test_cases.append(case)

    return {
        "module": sheet_name.lower(),
        "source": f"Converted from Excel sheet '{sheet_name}'",
        "data_version": 1,
        "test_cases": test_cases,
    }


def convert_workbook(workbook_path: str, output_dir: str, sheet_filter: str = None):
    """Convert all (or specific) sheets from a workbook to YAML files."""
    wb = load_workbook(workbook_path, read_only=True)
    sheets = wb.sheetnames
    wb.close()

    # Skip Master and utility sheets
    skip_sheets = {"Master", "Bill details", "Sheet1", "Sheet2", "Sheet3"}

    output_path = Path(output_dir)
    output_path.mkdir(parents=True, exist_ok=True)

    converted = 0
    for sheet_name in sheets:
        if sheet_name in skip_sheets:
            continue

        if sheet_filter and sheet_name != sheet_filter:
            continue

        print(f"Converting: {sheet_name}...", end=" ")
        data = excel_sheet_to_yaml(workbook_path, sheet_name)

        if data and data["test_cases"]:
            # Generate YAML filename from sheet name
            filename = sheet_name.lower().replace(" ", "_").replace("&", "_") + ".yml"
            yaml_path = output_path / filename

            with open(yaml_path, "w", encoding="utf-8") as f:
                yaml.dump(data, f, default_flow_style=False, allow_unicode=True, sort_keys=False)

            print(f"✅ {len(data['test_cases'])} test cases → {yaml_path}")
            converted += 1
        else:
            print("⏭️ Empty or invalid")

    print(f"\n{'═' * 50}")
    print(f"Converted {converted} sheets to YAML in {output_path}")


def main():
    parser = argparse.ArgumentParser(description="Convert Sparqla Excel test data to YAML")
    parser.add_argument("--input", "-i", required=True, help="Path to Excel workbook")
    parser.add_argument("--output", "-o", default="./data/", help="Output directory for YAML files")
    parser.add_argument("--sheet", "-s", default=None, help="Convert only this sheet (optional)")

    args = parser.parse_args()

    if not Path(args.input).exists():
        print(f"❌ File not found: {args.input}")
        sys.exit(1)

    convert_workbook(args.input, args.output, args.sheet)


if __name__ == "__main__":
    main()
