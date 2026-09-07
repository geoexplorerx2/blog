<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class FooterManager
{
    private static function getDb(): ?PDO
    {
        if (class_exists('Database')) {
            return Database::getConnection();
        }
        try {
            return new PDO("mysql:host=localhost;dbname=q_db;charset=utf8mb4", "root", "root", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function ensureTableExists(): void
    {
        $db = self::getDb();
        if ($db === null) return;

        $db->exec("CREATE TABLE IF NOT EXISTS footer_settings (
            id INT PRIMARY KEY,
            brand_initials VARCHAR(10) DEFAULT 'FN',
            brand_name VARCHAR(150) DEFAULT 'Farshad Nabizade',
            brand_title VARCHAR(150) DEFAULT 'Full-Stack Software Engineer',
            brand_bio TEXT,
            availability_status VARCHAR(150) DEFAULT 'Available for Engineering Opportunities',
            nav_links JSON,
            technologies JSON,
            email VARCHAR(150) DEFAULT 'farshad.nabizade@gmail.com',
            phone VARCHAR(50) DEFAULT '+98 912 345 6789',
            location VARCHAR(150) DEFAULT 'Tehran, Iran',
            contact_note VARCHAR(255) DEFAULT 'Feel free to reach out for collaborations, technical inquiries, or consulting.',
            copyright_text VARCHAR(255) DEFAULT 'All rights reserved. • Engineered with modern web standards.',
            visible_sections JSON,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $count = (int)$db->query("SELECT COUNT(*) FROM footer_settings WHERE id = 1")->fetchColumn();
        if ($count === 0) {
            $navLinks = [
                ["label" => "Questions Base", "url" => "index.php"],
                ["label" => "Projects Portfolio", "url" => "projects.php"],
                ["label" => "Freelance Hub", "url" => "freelance.php"],
                ["label" => "Resume & CV", "url" => "profile.php"],
                ["label" => "Technical Skills", "url" => "profile.php#skills"],
                ["label" => "Career Timeline", "url" => "profile.php#experience"]
            ];

            $technologies = [
                "React", "Next.js (SSR)", "TypeScript", "PHP & Laravel", "Node.js",
                "Tailwind CSS", "Redux Toolkit", "MySQL & Redis", "Docker & Nginx", "RESTful APIs", "Clean Code"
            ];

            $visibleSections = [
                "brand" => true,
                "nav" => true,
                "tech" => true,
                "contact" => true
            ];

            $stmt = $db->prepare("INSERT INTO footer_settings (
                id, brand_initials, brand_name, brand_title, brand_bio, availability_status,
                nav_links, technologies, email, phone, location, contact_note, copyright_text, visible_sections
            ) VALUES (
                1, :brand_initials, :brand_name, :brand_title, :brand_bio, :availability_status,
                :nav_links, :technologies, :email, :phone, :location, :contact_note, :copyright_text, :visible_sections
            )");

            $stmt->execute([
                ':brand_initials' => 'FN',
                ':brand_name' => 'Farshad Nabizade',
                ':brand_title' => 'Full-Stack Software Engineer',
                ':brand_bio' => 'Dedicated to architecting high-performance web applications, scalable APIs, reactive frontends, and comprehensive computer science knowledge bases.',
                ':availability_status' => 'Available for Engineering Opportunities',
                ':nav_links' => json_encode($navLinks, JSON_UNESCAPED_UNICODE),
                ':technologies' => json_encode($technologies, JSON_UNESCAPED_UNICODE),
                ':email' => 'farshad.nabizade@gmail.com',
                ':phone' => '+98 912 345 6789',
                ':location' => 'Tehran, Iran',
                ':contact_note' => 'Feel free to reach out for collaborations, technical inquiries, or consulting.',
                ':copyright_text' => 'All rights reserved. • Engineered with modern web standards.',
                ':visible_sections' => json_encode($visibleSections, JSON_UNESCAPED_UNICODE)
            ]);
        }
    }

    public static function getSettings(): array
    {
        self::ensureTableExists();
        $db = self::getDb();
        if ($db === null) {
            return self::getDefaults();
        }

        $stmt = $db->query("SELECT * FROM footer_settings WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch();
        if (!$row) {
            return self::getDefaults();
        }

        $row['nav_links'] = !empty($row['nav_links']) ? json_decode($row['nav_links'], true) : [];
        $row['technologies'] = !empty($row['technologies']) ? json_decode($row['technologies'], true) : [];
        $row['visible_sections'] = !empty($row['visible_sections']) ? json_decode($row['visible_sections'], true) : [
            "brand" => true,
            "nav" => true,
            "tech" => true,
            "contact" => true
        ];

        return $row;
    }

    public static function getDefaults(): array
    {
        return [
            'id' => 1,
            'brand_initials' => 'FN',
            'brand_name' => 'Farshad Nabizade',
            'brand_title' => 'Full-Stack Software Engineer',
            'brand_bio' => 'Dedicated to architecting high-performance web applications, scalable APIs, reactive frontends, and comprehensive computer science knowledge bases.',
            'availability_status' => 'Available for Engineering Opportunities',
            'nav_links' => [
                ["label" => "Questions Base", "url" => "index.php"],
                ["label" => "Projects Portfolio", "url" => "projects.php"],
                ["label" => "Freelance Hub", "url" => "freelance.php"],
                ["label" => "Resume & CV", "url" => "profile.php"],
                ["label" => "Technical Skills", "url" => "profile.php#skills"],
                ["label" => "Career Timeline", "url" => "profile.php#experience"]
            ],
            'technologies' => [
                "React", "Next.js (SSR)", "TypeScript", "PHP & Laravel", "Node.js",
                "Tailwind CSS", "Redux Toolkit", "MySQL & Redis", "Docker & Nginx", "RESTful APIs", "Clean Code"
            ],
            'email' => 'farshad.nabizade@gmail.com',
            'phone' => '+98 912 345 6789',
            'location' => 'Tehran, Iran',
            'contact_note' => 'Feel free to reach out for collaborations, technical inquiries, or consulting.',
            'copyright_text' => 'All rights reserved. • Engineered with modern web standards.',
            'visible_sections' => [
                "brand" => true,
                "nav" => true,
                "tech" => true,
                "contact" => true
            ]
        ];
    }
}

// -------------------------------------------------------------
// Shared API Action: save_footer
// -------------------------------------------------------------
if (isset($_GET['api_action']) && $_GET['api_action'] === 'save_footer') {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?? $_POST;

    if (!Auth::isLoggedIn()) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Administrator authentication required.', 'require_login' => true]);
        exit;
    }

    $db = (class_exists('Database') && Database::getConnection() !== null) ? Database::getConnection() : new PDO("mysql:host=localhost;dbname=q_db;charset=utf8mb4", "root", "root", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($db === null) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
        exit;
    }

    FooterManager::ensureTableExists();

    $brandInitials = trim($inputData['brand_initials'] ?? 'FN');
    $brandName = trim($inputData['brand_name'] ?? 'Farshad Nabizade');
    $brandTitle = trim($inputData['brand_title'] ?? 'Full-Stack Software Engineer');
    $brandBio = trim($inputData['brand_bio'] ?? '');
    $availability = trim($inputData['availability_status'] ?? '');
    $email = trim($inputData['email'] ?? '');
    $phone = trim($inputData['phone'] ?? '');
    $location = trim($inputData['location'] ?? '');
    $contactNote = trim($inputData['contact_note'] ?? '');
    $copyright = trim($inputData['copyright_text'] ?? 'All rights reserved.');

    $navLinks = is_array($inputData['nav_links'] ?? null) ? $inputData['nav_links'] : [];
    $technologies = is_array($inputData['technologies'] ?? null) ? $inputData['technologies'] : [];
    $visibleSections = is_array($inputData['visible_sections'] ?? null) ? $inputData['visible_sections'] : [
        "brand" => true, "nav" => true, "tech" => true, "contact" => true
    ];

    try {
        $stmt = $db->prepare("UPDATE footer_settings SET
            brand_initials = :brand_initials,
            brand_name = :brand_name,
            brand_title = :brand_title,
            brand_bio = :brand_bio,
            availability_status = :availability_status,
            nav_links = :nav_links,
            technologies = :technologies,
            email = :email,
            phone = :phone,
            location = :location,
            contact_note = :contact_note,
            copyright_text = :copyright_text,
            visible_sections = :visible_sections
            WHERE id = 1");

        $stmt->execute([
            ':brand_initials' => $brandInitials,
            ':brand_name' => $brandName,
            ':brand_title' => $brandTitle,
            ':brand_bio' => $brandBio,
            ':availability_status' => $availability,
            ':nav_links' => json_encode($navLinks, JSON_UNESCAPED_UNICODE),
            ':technologies' => json_encode($technologies, JSON_UNESCAPED_UNICODE),
            ':email' => $email,
            ':phone' => $phone,
            ':location' => $location,
            ':contact_note' => $contactNote,
            ':copyright_text' => $copyright,
            ':visible_sections' => json_encode($visibleSections, JSON_UNESCAPED_UNICODE)
        ]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Footer settings updated successfully!']);
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function renderGlobalFooter(): void
{
    $footer = FooterManager::getSettings();
    $visible = $footer['visible_sections'] ?? ["brand" => true, "nav" => true, "tech" => true, "contact" => true];
    $navLinks = is_array($footer['nav_links']) ? $footer['nav_links'] : [];
    $technologies = is_array($footer['technologies']) ? $footer['technologies'] : [];
    ?>
    <!-- Premium Global Footer (Editable & Dynamic) -->
    <footer class="app-global-footer">
        <div class="footer-inner-container">
            <?php if (Auth::isLoggedIn()): ?>
            <!-- Footer Edit Button for Admin Only -->
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1.25rem;">
                <button type="button" class="btn-edit-footer" id="editFooterTriggerBtn" title="Edit or Manage Footer Sections">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    <span>Edit Footer Sections</span>
                </button>
            </div>
            <?php endif; ?>

            <div class="footer-grid">
                <!-- Col 1: Brand & Profile -->
                <?php if (!empty($visible['brand'])): ?>
                <div class="footer-col brand-col">
                    <div class="footer-brand-title">
                        <span class="brand-avatar"><?php echo htmlspecialchars($footer['brand_initials'] ?? 'FN'); ?></span>
                        <div>
                            <h3><?php echo htmlspecialchars($footer['brand_name'] ?? 'Farshad Nabizade'); ?></h3>
                            <p class="brand-subtitle"><?php echo htmlspecialchars($footer['brand_title'] ?? 'Full-Stack Software Engineer'); ?></p>
                        </div>
                    </div>
                    <?php if (!empty($footer['brand_bio'])): ?>
                        <p class="brand-bio"><?php echo htmlspecialchars($footer['brand_bio']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($footer['availability_status'])): ?>
                        <div class="availability-badge">
                            <span class="status-pulse"></span>
                            <span><?php echo htmlspecialchars($footer['availability_status']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Col 2: Platform Navigation -->
                <?php if (!empty($visible['nav'])): ?>
                <div class="footer-col">
                    <h4 class="footer-heading">Platform Hub</h4>
                    <ul class="footer-nav-list">
                        <?php foreach ($navLinks as $nl): ?>
                            <?php if (!empty($nl['label']) && !empty($nl['url'])): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($nl['url']); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                        <?php echo htmlspecialchars($nl['label']); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Col 3: Core Technology Stack -->
                <?php if (!empty($visible['tech'])): ?>
                <div class="footer-col">
                    <h4 class="footer-heading">Technologies</h4>
                    <div class="footer-tech-cloud">
                        <?php foreach ($technologies as $t): ?>
                            <?php if (!empty($t)): ?>
                                <span class="tech-tag-pill"><?php echo htmlspecialchars((string)$t); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Col 4: Connect & Contact -->
                <?php if (!empty($visible['contact'])): ?>
                <div class="footer-col">
                    <h4 class="footer-heading">Connect &amp; Contact</h4>
                    <?php if (!empty($footer['contact_note'])): ?>
                        <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.85rem;"><?php echo htmlspecialchars($footer['contact_note']); ?></p>
                    <?php endif; ?>
                    <div class="contact-links-list">
                        <?php if (!empty($footer['email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($footer['email']); ?>" class="contact-item-link">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                <span><?php echo htmlspecialchars($footer['email']); ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($footer['phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($footer['phone']); ?>" class="contact-item-link">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                <span><?php echo htmlspecialchars($footer['phone']); ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($footer['location'])): ?>
                            <div class="contact-item-link location">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <span><?php echo htmlspecialchars($footer['location']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer Bottom Bar -->
            <div class="footer-bottom-bar">
                <div class="footer-copyright">
                    &copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($footer['brand_name'] ?? 'Farshad Nabizade'); ?></strong>. <?php echo htmlspecialchars($footer['copyright_text'] ?? 'All rights reserved.'); ?>
                </div>
                <button type="button" class="btn-footer-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Scroll to Top">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
                    <span>Back to Top</span>
                </button>
            </div>
        </div>
    </footer>

    <?php if (Auth::isLoggedIn()): ?>
    <!-- Edit Footer Modal -->
    <div class="modal-overlay" id="editFooterModal">
        <style>
            #editFooterModal .modal-card {
                max-width: 720px;
                width: 95%;
                max-height: 88vh;
                height: 88vh;
                display: flex;
                flex-direction: column;
                overflow: hidden !important;
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                border-radius: 14px;
            }
            #editFooterForm {
                display: flex;
                flex-direction: column;
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }
            #editFooterModal .modal-body {
                flex: 1;
                overflow-y: auto !important;
                padding: 1.25rem 1.5rem;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            #editFooterModal .modal-footer {
                display: flex;
                justify-content: flex-end;
                align-items: center;
                gap: 0.75rem;
                padding: 1rem 1.5rem;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                border-radius: 0 0 14px 14px;
                flex-shrink: 0;
                position: sticky;
                bottom: 0;
                z-index: 10;
            }
            #editFooterModal .btn-footer-cancel {
                background: #f1f5f9;
                color: #475569;
                border: 1px solid #cbd5e1;
                padding: 0.6rem 1.2rem;
                border-radius: 8px;
                font-size: 0.9rem;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                font-family: inherit;
                transition: all 0.15s ease;
            }
            #editFooterModal .btn-footer-cancel:hover {
                background: #e2e8f0;
                color: #1e293b;
            }
            #editFooterModal .btn-footer-confirm {
                background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
                color: #ffffff;
                border: none;
                padding: 0.65rem 1.4rem;
                border-radius: 8px;
                font-size: 0.92rem;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                font-family: inherit;
                transition: all 0.15s ease;
            }
            #editFooterModal .btn-footer-confirm:hover {
                background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
                transform: translateY(-1px);
                box-shadow: 0 6px 18px rgba(2, 132, 199, 0.45);
            }
            #editFooterModal .btn-header-quick-save {
                background: #22c55e;
                color: white;
                border: none;
                padding: 0.35rem 0.8rem;
                border-radius: 6px;
                font-size: 0.8rem;
                font-weight: 700;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                font-family: inherit;
                margin-left: auto;
                margin-right: 0.75rem;
                transition: all 0.15s ease;
            }
            #editFooterModal .btn-header-quick-save:hover {
                background: #16a34a;
            }
        </style>
        <div class="modal-card">
            <div class="modal-header">
                <h3 style="display:flex; align-items:center; gap:0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit or Delete Footer Sections
                </h3>
                <button type="button" class="btn-header-quick-save" id="quickSaveFooterBtn" title="Quick Confirm &amp; Save">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Save</span>
                </button>
                <button type="button" class="close-btn" id="closeFooterModalBtn">&times;</button>
            </div>
            <form id="editFooterForm">
                <div class="modal-body">
                    <!-- Section Visibility Toggles -->
                    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 0.85rem 1rem;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: var(--navy-800); display: block; margin-bottom: 0.45rem;">
                            Visible Footer Columns (Check to show, uncheck to hide/delete from view):
                        </span>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.85rem; cursor:pointer;">
                                <input type="checkbox" id="ft_visBrand" <?php echo !empty($visible['brand']) ? 'checked' : ''; ?>>
                                <span>Brand &amp; Bio</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.85rem; cursor:pointer;">
                                <input type="checkbox" id="ft_visNav" <?php echo !empty($visible['nav']) ? 'checked' : ''; ?>>
                                <span>Platform Hub</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.85rem; cursor:pointer;">
                                <input type="checkbox" id="ft_visTech" <?php echo !empty($visible['tech']) ? 'checked' : ''; ?>>
                                <span>Technologies</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.85rem; cursor:pointer;">
                                <input type="checkbox" id="ft_visContact" <?php echo !empty($visible['contact']) ? 'checked' : ''; ?>>
                                <span>Connect &amp; Contact</span>
                            </label>
                        </div>
                    </div>

                    <!-- Column 1: Brand & Profile Info -->
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="ft_brandName">Full Name / Brand Name</label>
                            <input type="text" id="ft_brandName" value="<?php echo htmlspecialchars($footer['brand_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="ft_brandInitials">Avatar Initials (1-3 chars)</label>
                            <input type="text" id="ft_brandInitials" value="<?php echo htmlspecialchars($footer['brand_initials'] ?? 'FN'); ?>" maxlength="4">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="ft_brandTitle">Professional Headline / Subtitle</label>
                            <input type="text" id="ft_brandTitle" value="<?php echo htmlspecialchars($footer['brand_title'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="ft_availability">Availability Status Badge</label>
                            <input type="text" id="ft_availability" value="<?php echo htmlspecialchars($footer['availability_status'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ft_brandBio">Brand Mission &amp; Bio Statement</label>
                        <textarea id="ft_brandBio" rows="2"><?php echo htmlspecialchars($footer['brand_bio'] ?? ''); ?></textarea>
                    </div>

                    <!-- Column 2: Platform Navigation Links (JSON format) -->
                    <div class="form-group">
                        <label for="ft_navLinksJson">Platform Hub Navigation Links (JSON Array with label &amp; url)</label>
                        <textarea id="ft_navLinksJson" class="code-area" rows="4"><?php echo htmlspecialchars(json_encode($navLinks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                    </div>

                    <!-- Column 3: Technologies (Comma-separated) -->
                    <div class="form-group">
                        <label for="ft_technologies">Technologies Tag Cloud (Comma-separated)</label>
                        <input type="text" id="ft_technologies" value="<?php echo htmlspecialchars(implode(', ', $technologies)); ?>">
                    </div>

                    <!-- Column 4: Contact Info -->
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="ft_email">Email Address</label>
                            <input type="email" id="ft_email" value="<?php echo htmlspecialchars($footer['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="ft_phone">Phone / Mobile</label>
                            <input type="text" id="ft_phone" value="<?php echo htmlspecialchars($footer['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="ft_location">Location / City</label>
                            <input type="text" id="ft_location" value="<?php echo htmlspecialchars($footer['location'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="ft_contactNote">Contact Note / Description</label>
                            <input type="text" id="ft_contactNote" value="<?php echo htmlspecialchars($footer['contact_note'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ft_copyright">Footer Copyright Note</label>
                        <input type="text" id="ft_copyright" value="<?php echo htmlspecialchars($footer['copyright_text'] ?? ''); ?>">
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem; padding: 1.1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 16px 16px;">
                    <button type="button" class="btn-footer-cancel" id="cancelFooterModalBtn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.65rem 1.25rem; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem; font-family: inherit;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        <span>Cancel</span>
                    </button>
                    <button type="submit" class="btn-footer-confirm" id="saveFooterSettingsBtn" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; border: none; padding: 0.65rem 1.4rem; border-radius: 8px; font-size: 0.9rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35); display: inline-flex; align-items: center; gap: 0.5rem; font-family: inherit;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Confirm &amp; Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        const editTrigger = document.getElementById('editFooterTriggerBtn');
        const footerModal = document.getElementById('editFooterModal');
        const closeBtn = document.getElementById('closeFooterModalBtn');
        const cancelBtn = document.getElementById('cancelFooterModalBtn');
        const footerForm = document.getElementById('editFooterForm');
        const saveBtn = document.getElementById('saveFooterSettingsBtn');
        const quickSaveBtn = document.getElementById('quickSaveFooterBtn');

        function openFooterModal() {
            if (footerModal) footerModal.classList.add('active');
        }

        function closeFooterModal() {
            if (footerModal) footerModal.classList.remove('active');
        }

        if (quickSaveBtn && footerForm) {
            quickSaveBtn.onclick = function() {
                footerForm.requestSubmit ? footerForm.requestSubmit() : footerForm.dispatchEvent(new Event('submit', { cancelable: true }));
            };
        }

        if (editTrigger) {
            editTrigger.onclick = function() {
                if (typeof isAuthenticated !== 'undefined' && !isAuthenticated) {
                    if (typeof openLoginModal === 'function') {
                        openLoginModal(() => openFooterModal(), 'Administrator login required to edit footer.');
                    } else {
                        alert('Administrator authentication required to edit footer.');
                    }
                    return;
                }
                openFooterModal();
            };
        }

        if (closeBtn) closeBtn.onclick = closeFooterModal;
        if (cancelBtn) cancelBtn.onclick = closeFooterModal;
        if (footerModal) {
            footerModal.onclick = function(e) {
                if (e.target === footerModal) closeFooterModal();
            };
        }

        if (footerForm) {
            footerForm.onsubmit = async function(e) {
                e.preventDefault();

                let navLinksParsed = [];
                try {
                    navLinksParsed = JSON.parse(document.getElementById('ft_navLinksJson').value);
                } catch (err) {
                    alert('Invalid JSON formatting for Platform Links.');
                    return;
                }

                const rawTech = document.getElementById('ft_technologies').value;
                const techArray = rawTech.split(',').map(t => t.trim()).filter(Boolean);

                const payload = {
                    brand_name: document.getElementById('ft_brandName').value.trim(),
                    brand_initials: document.getElementById('ft_brandInitials').value.trim(),
                    brand_title: document.getElementById('ft_brandTitle').value.trim(),
                    availability_status: document.getElementById('ft_availability').value.trim(),
                    brand_bio: document.getElementById('ft_brandBio').value.trim(),
                    nav_links: navLinksParsed,
                    technologies: techArray,
                    email: document.getElementById('ft_email').value.trim(),
                    phone: document.getElementById('ft_phone').value.trim(),
                    location: document.getElementById('ft_location').value.trim(),
                    contact_note: document.getElementById('ft_contactNote').value.trim(),
                    copyright_text: document.getElementById('ft_copyright').value.trim(),
                    visible_sections: {
                        brand: document.getElementById('ft_visBrand').checked,
                        nav: document.getElementById('ft_visNav').checked,
                        tech: document.getElementById('ft_visTech').checked,
                        contact: document.getElementById('ft_visContact').checked
                    }
                };

                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span>Saving Changes...</span>';

                try {
                    const currentScript = window.location.pathname.split('/').pop() || 'index.php';
                    const res = await fetch(`${currentScript}?api_action=save_footer`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const text = await res.text();
                    let result = null;
                    try { result = JSON.parse(text); } catch (e) { throw new Error('Invalid server response'); }

                    if (res.status === 401 || (result && result.require_login)) {
                        alert('Administrator session required. Please log in.');
                        if (typeof openLoginModal === 'function') {
                            openLoginModal(() => footerForm.dispatchEvent(new Event('submit')));
                        }
                        return;
                    }

                    if (result && result.success) {
                        closeFooterModal();
                        if (typeof showToast === 'function') {
                            showToast('Footer settings updated successfully!', 'success');
                        }
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        alert((result && result.error) || 'Failed to save footer settings.');
                    }
                } catch (err) {
                    alert(err.message || 'Error saving footer settings.');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Confirm &amp; Save Changes</span>';
                }
            };
        }
    })();
    </script>
    <?php endif; ?>
    <?php
}
