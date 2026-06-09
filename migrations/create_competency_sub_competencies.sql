-- Create competency_sub_competencies table
CREATE TABLE IF NOT EXISTS competency_sub_competencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competency_id INT NOT NULL,
    sub_competency_name VARCHAR(255) NOT NULL,
    sub_competency_level VARCHAR(100),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    CONSTRAINT fk_competency_id FOREIGN KEY (competency_id) 
    REFERENCES competencies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Create index for faster queries
    INDEX idx_competency_id (competency_id),
    INDEX idx_is_active (is_active)
);

-- Add sample data (optional - comment out if you don't want sample data)
-- This is just an example, adjust based on your actual competency IDs
/*
INSERT INTO competency_sub_competencies (competency_id, sub_competency_name, sub_competency_level, description, is_active) 
VALUES 
-- Replace competency_id with actual IDs from your competencies table
(1, 'Ahli Hygiene Industri Muda', 'Muda', 'Junior level technical personnel for industrial hygiene', 1),
(1, 'Ahli Hygiene Industri Madya', 'Madya', 'Middle level technical personnel for industrial hygiene', 1),
(1, 'Ahli Hygiene Industri Utama', 'Utama', 'Senior level technical personnel for industrial hygiene', 1);
*/
