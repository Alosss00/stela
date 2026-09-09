import sys
import re

files_to_update = [
    'resources/superadmin/reports.php',
    'resources/user/reports.php',
    'resources/dept/reports.php'
]

script_to_insert = '<script src="../../assets/js/report-toggles.js"></script>\n<script>'

for file_path in files_to_update:
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()

        # We want to replace the `function toggleSection(sectionId) { ... }` block
        # Since the contents might vary, let's use regex.
        # But for user/reports and dept/reports we just inserted a basic one.
        # Let's find `function toggleSection(sectionId) {` and find its matching closing brace.
        
        start_idx = content.find('function toggleSection(sectionId) {')
        if start_idx == -1:
            print(f"toggleSection not found in {file_path}")
            continue
            
        # Find the matching closing brace
        brace_count = 0
        end_idx = -1
        in_string = False
        string_char = ''
        
        for i in range(start_idx, len(content)):
            char = content[i]
            
            # Simple string handling
            if char in ("'", '"', '`'):
                if in_string and char == string_char:
                    if content[i-1] != '\\':
                        in_string = False
                elif not in_string:
                    in_string = True
                    string_char = char
                    
            if not in_string:
                if char == '{':
                    brace_count += 1
                elif char == '}':
                    brace_count -= 1
                    if brace_count == 0:
                        end_idx = i
                        break
                        
        if end_idx != -1:
            # Check if there is <script> just before it
            script_start = content.rfind('<script>', 0, start_idx)
            
            # If the script block only contains toggleSection, we can replace the whole script tag.
            # Otherwise we just replace the function body with nothing, and ensure our script is included somewhere.
            
            # Actually, simpler: replace the function with empty string.
            # And inject <script src="../../assets/js/report-toggles.js"></script> before the `<script>` tag containing it.
            
            new_content = content[:start_idx] + content[end_idx+1:]
            
            # Now insert the src tag right before the <script> tag that contained the function
            if script_start != -1:
                new_content = new_content[:script_start] + '<script src="../../assets/js/report-toggles.js"></script>\n' + new_content[script_start:]
            
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
                
            print(f"Successfully updated {file_path}")
        else:
            print(f"Could not find closing brace in {file_path}")
            
    except Exception as e:
        print(f"Error processing {file_path}: {e}")
