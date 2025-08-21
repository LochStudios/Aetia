<?php
// models/Contact.php - Contact model for Aetia Talent Agency

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailService.php';

class Contact {
    private $db;
    private $mysqli;

    public function __construct() {
        $this->db = new Database();
        $this->mysqli = $this->db->getConnection();
    }

    /**
     * Submit a new contact form
     */
    public function submitContactForm($name, $email, $subject, $message, $ipAddress = null, $userAgent = null) {
        try {
            // Basic validation
            if (empty($name) || empty($email) || empty($message)) {
                return ['success' => false, 'message' => 'All fields are required'];
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            // Get GeoIP information using free IP geolocation service
            $geoData = null;
            if ($ipAddress && $ipAddress !== '127.0.0.1' && $ipAddress !== '::1') {
                try {
                    // Use ip-api.com (free service, 1000 requests/hour)
                    $geoUrl = "http://ip-api.com/json/{$ipAddress}?fields=status,message,country,countryCode,region,regionName,city,lat,lon,timezone,query";
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 5, // 5 second timeout
                            'user_agent' => 'Aetia-Contact-Form/1.0'
                        ]
                    ]);
                    $response = file_get_contents($geoUrl, false, $context);
                    if ($response !== false) {
                        $geoRecord = json_decode($response, true);
                        if ($geoRecord && $geoRecord['status'] === 'success') {
                            $geoData = json_encode([
                                'country_code' => $geoRecord['countryCode'] ?? null,
                                'country_name' => $geoRecord['country'] ?? null,
                                'region' => $geoRecord['regionName'] ?? null,
                                'region_code' => $geoRecord['region'] ?? null,
                                'city' => $geoRecord['city'] ?? null,
                                'latitude' => $geoRecord['lat'] ?? null,
                                'longitude' => $geoRecord['lon'] ?? null,
                                'timezone' => $geoRecord['timezone'] ?? null,
                                'ip_address' => $geoRecord['query'] ?? $ipAddress,
                                'service' => 'ip-api.com',
                                'timestamp' => date('Y-m-d H:i:s')
                            ]);
                        } else {
                            // API returned an error
                            $geoData = json_encode([
                                'ip_address' => $ipAddress,
                                'error' => $geoRecord['message'] ?? 'Unknown API error',
                                'service' => 'ip-api.com',
                                'timestamp' => date('Y-m-d H:i:s')
                            ]);
                        }
                    } else {
                        // Failed to connect to API
                        $geoData = json_encode([
                            'ip_address' => $ipAddress,
                            'error' => 'Failed to connect to geolocation service',
                            'service' => 'ip-api.com',
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                    }
                } catch (Exception $e) {
                    // GeoIP lookup failed, store basic info
                    error_log('GeoIP lookup failed for IP ' . $ipAddress . ': ' . $e->getMessage());
                    $geoData = json_encode([
                        'ip_address' => $ipAddress,
                        'error' => 'GeoIP lookup exception: ' . $e->getMessage(),
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                // Local IP or invalid IP
                $geoData = json_encode([
                    'ip_address' => $ipAddress,
                    'note' => 'Local/private IP address - no geolocation available',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            // Server-side spam checks
            $isSpam = false;
            $spamReasons = [];
            // Simple heuristics: suspicious phrases often used by promotional/spam messages
            $suspiciousPhrases = [
                'prices start from', 'our prices', 'just $', 'just £', 'just €', 'cost of sending', 'cost of', 'we offer', 'we offer you',
                'try our service', 'try our service for free', 'free trial', 'samples', 'see samples', 'portfolio', 'click here', 'visit',
                'video to explain', 'engaging video', 'commercial messages', 'deliverability', 'automatically generated', 'contact us', 'telegram',
                'wa.me', 't.me', 'whatsapp', 'we only use chat', 'send out', '50,000', 'one million', '1 million', '50k'
            ];
            $lowerMsg = strtolower($message);
            foreach ($suspiciousPhrases as $phrase) {
                if (strpos($lowerMsg, $phrase) !== false) {
                    $isSpam = true;
                    $spamReasons[] = 'suspicious_phrase:' . $phrase;
                }
            }
            // Count URLs and link-like patterns (http(s), www, t.me, wa.me). Multiple links increase spam likelihood
            $urlPattern = '/https?:\/\/|www\.|t\.me\/|wa\.me\/|telegram\.me\//i';
            preg_match_all($urlPattern, $message, $urlMatches);
            $urlCount = count($urlMatches[0]);
            if ($urlCount > 0) {
                $isSpam = true; // any external link is suspicious for contact form
                $spamReasons[] = 'contains_url_count:' . $urlCount;
            }
            if ($urlCount >= 2) {
                $spamReasons[] = 'multiple_links';
            }
            // Detect phone numbers or long digit sequences that look like contact numbers
            if (preg_match('/\+?\d[\d\s\-()]{6,}\d/', $message)) {
                $isSpam = true;
                $spamReasons[] = 'contains_phone_number';
            }
            // Detect currency or large-quantity offers ($59, one million, 50,000 etc.)
            if (preg_match('/\$\s?\d+|\b(one million|1 million|\d{1,3}(,|\s)?\d{3}|50k|50,000|\bmillion\b)\b/i', $message)) {
                $isSpam = true;
                $spamReasons[] = 'contains_currency_or_large_offer';
            }
            // Very short generic messages (<=40 chars) that are promotional
            if (strlen(trim($message)) <= 40 && preg_match('/\b(pricing|video|samples|portfolio|services|offer|free)\b/i', $message)) {
                $isSpam = true;
                $spamReasons[] = 'short_promotional';
            }
            // Prevent spam - check for recent submissions from same IP
            if ($ipAddress) {
                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(*) as count 
                    FROM contact_submissions 
                    WHERE ip_address = ? 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $stmt->bind_param("s", $ipAddress);
                $stmt->execute();
                $result = $stmt->get_result();
                $spamCheck = $result->fetch_assoc();
                $stmt->close();
                if ($spamCheck['count'] >= 5) {
                    return ['success' => false, 'message' => 'Too many submissions from your IP address. Please try again later.'];
                }
            }
            // Insert the contact submission
            // Append spam metadata to geo_data JSON
            $geoArray = json_decode($geoData, true) ?: [];
            $geoArray['spam'] = $isSpam ? true : false;
            if (!empty($spamReasons)) {
                $geoArray['spam_reasons'] = $spamReasons;
            }
            $geoArray['spam_checked_at'] = date('Y-m-d H:i:s');
            $geoData = json_encode($geoArray);
            $status = $isSpam ? 'spam' : 'new';
            $priority = $isSpam ? 'low' : 'normal';
            $stmt = $this->mysqli->prepare("
                INSERT INTO contact_submissions (name, email, subject, message, ip_address, user_agent, geo_data, status, priority) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssssss", $name, $email, $subject, $message, $ipAddress, $userAgent, $geoData, $status, $priority);
            $result = $stmt->execute();
            $contactId = $this->mysqli->insert_id;
            $stmt->close();
            if ($result) {
                // Send email notification to admins
                try {
                    // If message is not flagged as spam, notify admins
                    if (!$isSpam) {
                        $emailService = new EmailService();
                        $contactData = [
                            'name' => $name,
                            'email' => $email,
                            'subject' => $subject,
                            'message' => $message
                        ];
                        $emailService->sendContactFormNotification($contactData);
                    } else {
                        error_log('Contact submission flagged as spam: reasons=' . implode(',', $spamReasons));
                    }
                } catch (Exception $e) {
                    // Log email error but don't fail the contact submission
                    error_log('Failed to send contact form email notification: ' . $e->getMessage());
                }
                return [
                    'success' => true, 
                    'message' => 'Thank you for your message. We will get back to you soon!',
                    'contact_id' => $contactId
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to submit your message. Please try again.'];
            }
        } catch (Exception $e) {
            error_log('Contact form submission error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again later.'];
        }
    }

    /**
     * Get all contact submissions for admin
     */
    public function getAllContactSubmissions($limit = 50, $offset = 0, $statusFilter = null) {
        try {
            $whereClause = "";
            $params = [];
            $types = "";
            if ($statusFilter && $statusFilter !== 'all') {
                $whereClause = "WHERE status = ?";
                $params[] = $statusFilter;
                $types .= "s";
            }
            $query = "
                SELECT 
                    id,
                    name,
                    email,
                    subject,
                    message,
                    status,
                    priority,
                    ip_address,
                    geo_data,
                    responded_by,
                    responded_at,
                    created_at,
                    updated_at
                FROM contact_submissions 
                $whereClause
                ORDER BY 
                    CASE status 
                        WHEN 'new' THEN 1 
                        WHEN 'read' THEN 2 
                        WHEN 'responded' THEN 3 
                        WHEN 'closed' THEN 4 
                    END,
                    CASE priority 
                        WHEN 'high' THEN 1 
                        WHEN 'normal' THEN 2 
                        WHEN 'low' THEN 3 
                    END,
                    created_at DESC 
                LIMIT ? OFFSET ?
            ";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            $stmt = $this->mysqli->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $submissions = [];
            while ($row = $result->fetch_assoc()) {
                $submissions[] = $row;
            }
            
            $stmt->close();
            return $submissions;
        } catch (Exception $e) {
            error_log('Get contact submissions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single contact submission
     */
    public function getContactSubmission($id) {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT 
                    cs.*,
                    u.username as responded_by_username
                FROM contact_submissions cs
                LEFT JOIN users u ON cs.responded_by = u.id
                WHERE cs.id = ?
            ");
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $submission = $result->fetch_assoc();
            $stmt->close();
            return $submission;
        } catch (Exception $e) {
            error_log('Get contact submission error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update contact submission status
     */
    public function updateStatus($id, $status, $userId = null) {
        try {
            $validStatuses = ['new', 'read', 'responded', 'closed'];
            if (!in_array($status, $validStatuses)) {
                return false;
            }
            if ($status === 'responded' && $userId) {
                $stmt = $this->mysqli->prepare("
                    UPDATE contact_submissions 
                    SET status = ?, responded_by = ?, responded_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param("sii", $status, $userId, $id);
            } else {
                $stmt = $this->mysqli->prepare("
                    UPDATE contact_submissions 
                    SET status = ? 
                    WHERE id = ?
                ");
                $stmt->bind_param("si", $status, $id);
            }
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log('Update contact status error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update contact submission priority
     */
    public function updatePriority($id, $priority) {
        try {
            $validPriorities = ['low', 'normal', 'high'];
            if (!in_array($priority, $validPriorities)) {
                return false;
            }
            $stmt = $this->mysqli->prepare("
                UPDATE contact_submissions 
                SET priority = ? 
                WHERE id = ?
            ");
            
            $stmt->bind_param("si", $priority, $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log('Update contact priority error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add response notes
     */
    public function addResponseNotes($id, $notes, $userId) {
        try {
            $stmt = $this->mysqli->prepare("
                UPDATE contact_submissions 
                SET response_notes = ?, responded_by = ?, responded_at = NOW(), status = 'responded' 
                WHERE id = ?
            ");
            
            $stmt->bind_param("sii", $notes, $userId, $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log('Add response notes error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get contact submission statistics
     */
    public function getContactStats() {
        try {
            $stats = [];
            // Total submissions
            $result = $this->mysqli->query("SELECT COUNT(*) as total FROM contact_submissions");
            $stats['total'] = $result->fetch_assoc()['total'];
            // By status
            $result = $this->mysqli->query("
                SELECT status, COUNT(*) as count 
                FROM contact_submissions 
                GROUP BY status
            ");
            while ($row = $result->fetch_assoc()) {
                $stats['status'][$row['status']] = $row['count'];
            }
            // Today's submissions
            $result = $this->mysqli->query("
                SELECT COUNT(*) as today 
                FROM contact_submissions 
                WHERE DATE(created_at) = CURDATE()
            ");
            $stats['today'] = $result->fetch_assoc()['today'];
            // This week's submissions
            $result = $this->mysqli->query("
                SELECT COUNT(*) as this_week 
                FROM contact_submissions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats['this_week'] = $result->fetch_assoc()['this_week'];
            return $stats;
        } catch (Exception $e) {
            error_log('Get contact stats error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a contact submission (admin only)
     */
    public function deleteContactSubmission($id) {
        try {
            $stmt = $this->mysqli->prepare("DELETE FROM contact_submissions WHERE id = ?");
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log('Delete contact submission error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get formatted location string from geo data
     */
    public function getLocationString($geoData) {
        if (empty($geoData)) {
            return null;
        }
        try {
            $data = json_decode($geoData, true);
            if (!$data) {
                return null;
            }
            // Handle error cases
            if (isset($data['error']) || isset($data['note'])) {
                return $data['error'] ?? $data['note'] ?? 'Location unavailable';
            }
            $location = [];
            if (!empty($data['city'])) {
                $location[] = $data['city'];
            }
            if (!empty($data['region'])) {
                $location[] = $data['region'];
            }
            if (!empty($data['country_name'])) {
                $location[] = $data['country_name'];
            }
            return !empty($location) ? implode(', ', $location) : 'Location unavailable';
        } catch (Exception $e) {
            return 'Location unavailable';
        }
    }

    /**
     * Get country flag emoji from country code
     */
    public function getCountryFlag($geoData) {
        if (empty($geoData)) {
            return null;
        }
        try {
            $data = json_decode($geoData, true);
            if (!$data || empty($data['country_code'])) {
                return null;
            }
            $countryCode = strtoupper($data['country_code']);
            
            // Convert country code to flag emoji
            $flag = '';
            for ($i = 0; $i < strlen($countryCode); $i++) {
                $flag .= mb_chr(ord($countryCode[$i]) - ord('A') + 0x1F1E6, 'UTF-8');
            }
            
            return $flag;
        } catch (Exception $e) {
            return null;
        }
    }
}
?>
