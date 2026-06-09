import os
from docx import Document
from docx.table import Table

os.chdir(r"c:\Users\agria\OneDrive\Documents\PROJECT MAGANG\windy-19-1-26")

file_path = r"assets\Draft Surat Penunjukan\Draft Contoh Surat Penunjukan Pengawas Operasional Pertambangan.docx"

print("STRUKTUR LENGKAP DOKUMEN")
print("="*120 + "\n")

doc = Document(file_path)

table_num = 0
for element in doc.element.body:
    if element.tag.endswith('tbl'):
        table_num += 1
        table = Table(element, doc)
        
        print(f"\n{'='*120}")
        print(f"TABEL #{table_num} - {len(table.rows)} baris x {len(table.columns)} kolom")
        print('='*120)
        
        for i, row in enumerate(table.rows):
            print(f"\nBARIS {i}:")
            for j, cell in enumerate(row.cells):
                text = cell.text.strip()
                if text:
                    # Tampilkan 200 karakter pertama
                    preview = text[:200] + "..." if len(text) > 200 else text
                    print(f"  Kolom {j}: {preview}")

print("\n" + "="*120)
