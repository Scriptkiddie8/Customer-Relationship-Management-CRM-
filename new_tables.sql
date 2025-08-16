drop table project_templates;
-- Table for storing project templates
CREATE TABLE project_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    default_deadline_days INT NOT NULL,  -- Number of working days for the default deadline
    default_manhours INT NOT NULL,  -- Default manhour limit for projects based on this template
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for storing projects
CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    template_id INT,  -- Nullable, in case the project is not based on a template
    deadline DATE NOT NULL,
    total_manhours INT NOT NULL,
    created_by INT NOT NULL,  -- User ID of the project creator
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES project_templates(template_id),
    FOREIGN KEY (created_by) REFERENCES users(id)  -- Correct foreign key reference to 'users(id)'
);

-- Table for storing subtasks within projects
CREATE TABLE subtasks (
    subtask_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    subtask_name VARCHAR(255) NOT NULL,
    assigned_to INT,  -- Nullable, in case the subtask is not yet assigned
    deadline DATE NOT NULL,
    estimated_manhours INT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Table for tracking time spent on subtasks
CREATE TABLE time_tracking (
    tracking_id INT AUTO_INCREMENT PRIMARY KEY,
    subtask_id INT NOT NULL,
    user_id INT NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NULL DEFAULT NULL,  -- Allow NULL values for 'end_time'
    duration INT,  -- Duration in minutes
    FOREIGN KEY (subtask_id) REFERENCES subtasks(subtask_id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);


-- Table for logging user activities
CREATE TABLE user_activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT,
    subtask_id INT,
    activity_type VARCHAR(255) NOT NULL,  -- e.g., 'created', 'updated', 'commented'
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (subtask_id) REFERENCES subtasks(subtask_id)
);

-- Table for storing comments and notes
CREATE TABLE comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    subtask_id INT,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    file_path VARCHAR(255),  -- Optional file upload path
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (subtask_id) REFERENCES subtasks(subtask_id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
