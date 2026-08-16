<?php
$files = [
    'user' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\user\reports.php',
    'dept' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\dept\reports.php',
    'admin' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\admin\reports.php'
];

foreach ($files as $role => $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Remove the wrapper if
    $content = str_replace('<!-- Resigned Employees -->
    <?php if ($resigned_employees && $resigned_employees->num_rows > 0): ?>', '<!-- Resigned Employees -->', $content);
    
    $content = preg_replace('/(<\/div>\s*<\/div>\s*)<\?php endif; \?>(\s*<\/div>\s*<!-- Request Rejection Details Modal -->)/', '$1$2', $content);
    $content = preg_replace('/(<\/div>\s*<\/div>\s*)<\?php endif; \?>(\s*<\/div>\s*<div id="requestRejectionModal")/', '$1$2', $content);
    
    // Add empty state inside tbody
    $emptyState = '<?php if (!$resigned_employees || $resigned_employees->num_rows === 0): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted" style="padding: 20px;">
                                No resigned employees data available
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php 
                        $resigned_employees->data_seek(0);
                        while ($row = $resigned_employees->fetch_assoc()): 
                        ?>';
                        
    $content = str_replace('<?php 
                        $resigned_employees->data_seek(0);
                        while ($row = $resigned_employees->fetch_assoc()): 
                        ?>', $emptyState, $content);
                        
    $content = str_replace('<?php endwhile; ?>
                    </tbody>', '<?php endwhile; endif; ?>
                    </tbody>', $content);

    file_put_contents($file, $content);
}
echo "Done";
