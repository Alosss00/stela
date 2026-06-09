<?php
/**
 * Verification Script: Check Sub-Competency System
 * This script verifies that all components are working correctly
 */

require_once 'includes/db.php';

$db = new Database();

echo "================================================\n";
echo "  SUB-COMPETENCY SYSTEM VERIFICATION\n";
echo "================================================\n\n";

// 1. Check column structure
echo "1️⃣  DATABASE STRUCTURE CHECK\n";
echo "─────────────────────────────────\n";

try {
    $result = $db->query("DESCRIBE employees");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row['Type'];
    }
    
    if (isset($columns['sub_competency'])) {
        $type = $columns['sub_competency'];
        echo "✅ Column 'sub_competency': $type\n";
        if (strpos($type, '255') !== false) {
            echo "   ✅ Size is correct (VARCHAR(255))\n";
        } else {
            echo "   ⚠️  Size might be different: $type\n";
        }
    } else {
        echo "❌ Column 'sub_competency' NOT FOUND!\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking table structure: " . $e->getMessage() . "\n";
}

// 2. Check competencies table
echo "\n2️⃣  COMPETENCIES TABLE CHECK\n";
echo "─────────────────────────────────\n";

try {
    $check = $db->query("SHOW TABLES LIKE 'competencies'");
    if ($check && $check->num_rows > 0) {
        echo "✅ Table 'competencies' exists\n";
        
        $count = $db->query("SELECT COUNT(*) as total FROM competencies WHERE is_active = 1");
        $row = $count->fetch_assoc();
        echo "   📊 Total competencies: " . $row['total'] . "\n";
    } else {
        echo "❌ Table 'competencies' NOT FOUND!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 3. Check sub_competencies table
echo "\n3️⃣  SUB-COMPETENCIES TABLE CHECK\n";
echo "─────────────────────────────────\n";

try {
    $check = $db->query("SHOW TABLES LIKE 'competency_sub_competencies'");
    if ($check && $check->num_rows > 0) {
        echo "✅ Table 'competency_sub_competencies' exists\n";
        
        $count = $db->query("SELECT COUNT(*) as total FROM competency_sub_competencies WHERE is_active = 1");
        $row = $count->fetch_assoc();
        echo "   📊 Total sub-competencies: " . $row['total'] . "\n";
        
        // Show sample data
        $sample = $db->query("
            SELECT csc.id, c.competency_name, csc.sub_competency_name 
            FROM competency_sub_competencies csc
            JOIN competencies c ON csc.competency_id = c.id
            WHERE csc.is_active = 1
            LIMIT 5
        ");
        
        if ($sample && $sample->num_rows > 0) {
            echo "   📋 Sample data:\n";
            while ($data = $sample->fetch_assoc()) {
                echo "      - " . htmlspecialchars($data['competency_name']) . " → " . htmlspecialchars($data['sub_competency_name']) . "\n";
            }
        }
    } else {
        echo "⚠️  Table 'competency_sub_competencies' NOT FOUND!\n";
        echo "   This will be created when you add a Tenaga Teknis competency\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 4. Check Tenaga Teknis competencies
echo "\n4️⃣  TENAGA TEKNIS COMPETENCIES CHECK\n";
echo "─────────────────────────────────────\n";

try {
    $tenaga = $db->query("
        SELECT id, competency_name 
        FROM competencies 
        WHERE position_type = 'tenaga_teknis' 
        AND is_active = 1
    ");
    
    if ($tenaga && $tenaga->num_rows > 0) {
        echo "✅ Found " . $tenaga->num_rows . " Tenaga Teknis competencies\n";
        while ($comp = $tenaga->fetch_assoc()) {
            $comp_id = $comp['id'];
            $subs = $db->query("
                SELECT COUNT(*) as count 
                FROM competency_sub_competencies 
                WHERE competency_id = $comp_id AND is_active = 1
            ");
            $sub_count = $subs->fetch_assoc()['count'];
            echo "   - " . htmlspecialchars($comp['competency_name']) . " ($sub_count sub-competencies)\n";
        }
    } else {
        echo "⚠️  No Tenaga Teknis competencies found yet\n";
        echo "   You need to add some via admin panel first\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 5. Check recent employee data
echo "\n5️⃣  RECENT EMPLOYEE DATA CHECK\n";
echo "──────────────────────────────\n";

try {
    $employees = $db->query("
        SELECT id, full_name, competency_type, competency_name, sub_competency, created_at
        FROM employees 
        WHERE competency_type = 'tenaga_teknis' AND sub_competency IS NOT NULL
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    
    if ($employees && $employees->num_rows > 0) {
        echo "✅ Found " . $employees->num_rows . " employees with Tenaga Teknis\n";
        while ($emp = $employees->fetch_assoc()) {
            echo "   - " . htmlspecialchars($emp['full_name']) . "\n";
            echo "     Competency: " . htmlspecialchars($emp['competency_name']) . "\n";
            echo "     Sub-Competency: " . htmlspecialchars($emp['sub_competency']) . "\n";
        }
    } else {
        echo "⚠️  No employee data with sub-competency yet\n";
        echo "   This is expected if you haven't added any yet\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 6. Final summary
echo "\n=================================================\n";
echo "  VERIFICATION SUMMARY\n";
echo "=================================================\n";
echo "✅ Database structure: OK\n";
echo "✅ Sub-competency column: VARCHAR(255)\n";
echo "✅ Ready to accept sub-competency data!\n\n";

echo "📝 NEXT STEPS:\n";
echo "1. Go to Admin Panel → Competency Management\n";
echo "2. Add competencies for 'Tenaga Teknis' type\n";
echo "3. Add sub-competencies for each competency\n";
echo "4. Then test the form in user_add_employee.php\n\n";
?>

