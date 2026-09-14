<?php

namespace App\Services;

use App\Contracts\FreelanceRepositoryInterface;
use App\Repositories\FreelanceRepository;
use PDO;

class FreelanceService
{
    private FreelanceRepositoryInterface $repository;

    public function __construct(?FreelanceRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new FreelanceRepository();
    }

    public function getRepository(): FreelanceRepositoryInterface
    {
        return $this->repository;
    }

    public function getTree(): array
    {
        return $this->repository->getFullStructure();
    }

    public function getSharedCompany(string $token): ?array
    {
        return $this->repository->getCompanyByShareToken($token);
    }

    public function getSharedProject(string $token): ?array
    {
        return $this->repository->getProjectByShareToken($token);
    }

    public function getProjectDetails(int $id): ?array
    {
        if ($this->repository instanceof FreelanceRepository) {
            return $this->repository->getProjectDetails($id);
        }
        return null;
    }

    public function calculateDurationAndPrice(string $startDate, string $endDate, string $startTime, string $endTime, float $hourlyRate): array
    {
        if (empty($startDate)) $startDate = date('Y-m-d');
        if (empty($endDate)) $endDate = $startDate;

        $startStr = "$startDate $startTime";
        $endStr = "$endDate $endTime";
        $start = strtotime($startStr);
        $end = strtotime($endStr);

        if ($start === false || $end === false) {
            $diffSeconds = 0;
        } elseif ($end <= $start) {
            if ($startDate === $endDate) {
                // Cross midnight on single day
                $startOnly = strtotime("1970-01-01 $startTime");
                $endOnly = strtotime("1970-01-01 $endTime");
                $diffSeconds = (24 * 3600 - $startOnly) + $endOnly;
            } else {
                $diffSeconds = 0;
            }
        } else {
            $diffSeconds = $end - $start;
        }

        $hours = round($diffSeconds / 3600, 2);
        $total = round($hours * $hourlyRate, 2);
        return [
            'duration_hours' => $hours,
            'total_price' => $total
        ];
    }

    public function saveCompany(array $data): int
    {
        return $this->repository->saveCompany($data);
    }

    public function deleteCompany(int $id): bool
    {
        return $this->repository->deleteCompany($id);
    }

    public function deleteAllCompanies(): void
    {
        if ($this->repository instanceof FreelanceRepository) {
            $this->repository->deleteAllCompanies();
        }
    }

    public function saveProject(array $data): int
    {
        return $this->repository->saveProject($data);
    }

    public function updateProjectCurrency(int $id, string $currency, ?float $hourlyRate = null, bool $applyToTasks = false): bool
    {
        if ($this->repository instanceof FreelanceRepository) {
            return $this->repository->updateProjectCurrency($id, $currency, $hourlyRate, $applyToTasks);
        }
        return false;
    }

    public function deleteProject(int $id): bool
    {
        return $this->repository->deleteProject($id);
    }

    public function saveTask(array $data): int
    {
        $startDate = trim($data['start_date'] ?? $data['task_date'] ?? date('Y-m-d'));
        $endDate = trim($data['end_date'] ?? $startDate);
        $startTime = trim($data['start_time'] ?? '09:00');
        $endTime = trim($data['end_time'] ?? '17:00');
        $rate = (float)($data['price_per_hour'] ?? 500000.00);

        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5) $endTime .= ':00';

        $calc = $this->calculateDurationAndPrice($startDate, $endDate, $startTime, $endTime, $rate);
        $data['duration_hours'] = $calc['duration_hours'];
        $data['total_price'] = $calc['total_price'];

        return $this->repository->saveTask($data);
    }

    public function deleteTask(int $id): bool
    {
        return $this->repository->deleteTask($id);
    }

    public function seedDemoData(): void
    {
        if ($this->repository instanceof FreelanceRepository) {
            $db = $this->repository->getDb();
            if ($db === null) return;

            // Company 1: Apex Cloud Technologies
            $stmt = $db->prepare("INSERT INTO freelance_companies (name, client_name, client_email, color, share_token) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Apex Cloud Technologies', 'David Miller', 'david@apexcloud.io', '#12466f', $this->repository->generateToken()]);
            $c1 = (int)$db->lastInsertId();

            // Company 2: Nexa Health Systems
            $stmt->execute(['Nexa Health Systems', 'Sarah Jenkins', 's.jenkins@nexahealth.com', '#0f766e', $this->repository->generateToken()]);
            $c2 = (int)$db->lastInsertId();

            // Project 1.1 under Apex
            $stmtProj = $db->prepare("INSERT INTO freelance_projects (company_id, title, description, hourly_rate, currency, status, share_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtProj->execute([
                $c1,
                'Enterprise API Microservices & Gateway',
                'Architecting and implementing high-throughput REST and GraphQL gateways with Redis caching and JWT authentication.',
                65.00,
                '$',
                'in_progress',
                $this->repository->generateToken()
            ]);
            $p1 = (int)$db->lastInsertId();

            // Project 1.2 under Apex
            $stmtProj->execute([
                $c1,
                'Kubernetes CI/CD Pipeline Automation',
                'Automated GitLab CI/CD runner deployment, Docker container image optimization, and canary release strategies.',
                70.00,
                '$',
                'completed',
                $this->repository->generateToken()
            ]);
            $p2 = (int)$db->lastInsertId();

            // Project 2.1 under Nexa Health
            $stmtProj->execute([
                $c2,
                'HIPAA Compliant Patient Telehealth Portal',
                'End-to-end encrypted video consultation module, appointment scheduling, and EHR database synchronization.',
                60.00,
                '$',
                'in_progress',
                $this->repository->generateToken()
            ]);
            $p3 = (int)$db->lastInsertId();

            // Tasks for Project 1.1
            $stmtTask = $db->prepare("INSERT INTO freelance_tasks (project_id, title, task_date, start_date, end_date, start_time, end_time, price_per_hour, duration_hours, total_price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $d1 = date('Y-m-d', strtotime('-4 days'));
            $cTime1 = $this->calculateDurationAndPrice($d1, $d1, '09:00:00', '12:30:00', 65.00);
            $stmtTask->execute([
                $p1,
                'Setup Redis Clustering & Caching Layer',
                $d1,
                $d1,
                $d1,
                '09:00:00',
                '12:30:00',
                65.00,
                $cTime1['duration_hours'],
                $cTime1['total_price'],
                'Configured distributed master-replica Redis cluster with automatic failover and low-latency cache invalidation hooks.',
                'completed'
            ]);

            $d2 = date('Y-m-d', strtotime('-2 days'));
            $cTime2 = $this->calculateDurationAndPrice($d2, $d2, '13:30:00', '17:45:00', 65.00);
            $stmtTask->execute([
                $p1,
                'JWT Token Refresh & RBAC Middleware',
                $d2,
                $d2,
                $d2,
                '13:30:00',
                '17:45:00',
                65.00,
                $cTime2['duration_hours'],
                $cTime2['total_price'],
                'Implemented asymmetric RSA256 signature verification, refresh token blacklisting, and granular endpoint permission gates.',
                'completed'
            ]);

            $d3 = date('Y-m-d');
            $cTime3 = $this->calculateDurationAndPrice($d3, $d3, '10:00:00', '14:15:00', 65.00);
            $stmtTask->execute([
                $p1,
                'Rate Limiting & DDoS Shield Integration',
                $d3,
                $d3,
                $d3,
                '10:00:00',
                '14:15:00',
                65.00,
                $cTime3['duration_hours'],
                $cTime3['total_price'],
                'Added token-bucket rate limiter per API key and integrated Cloudflare proxy origin header validation.',
                'in_progress'
            ]);

            // Tasks for Project 1.2
            $d4 = date('Y-m-d', strtotime('-6 days'));
            $cTime4 = $this->calculateDurationAndPrice($d4, $d4, '08:30:00', '12:00:00', 70.00);
            $stmtTask->execute([
                $p2,
                'Helm Charts & Multi-Stage Dockerfiles',
                $d4,
                $d4,
                $d4,
                '08:30:00',
                '12:00:00',
                70.00,
                $cTime4['duration_hours'],
                $cTime4['total_price'],
                'Standardized alpine-based multi-stage container builds, cutting bundle size by 62% and authored Helm value templates.',
                'completed'
            ]);

            // Tasks for Project 2.1
            $d5 = date('Y-m-d', strtotime('-1 days'));
            $cTime5 = $this->calculateDurationAndPrice($d5, $d5, '09:30:00', '13:00:00', 60.00);
            $stmtTask->execute([
                $p3,
                'WebRTC Signaling & Video Stream Encryption',
                $d5,
                $d5,
                $d5,
                '09:30:00',
                '13:00:00',
                60.00,
                $cTime5['duration_hours'],
                $cTime5['total_price'],
                'Integrated peer-to-peer WebRTC rooms with STUN/TURN fallback and strict AES-256 media packet encryption.',
                'completed'
            ]);
        }
    }
}
