<?php
/**
 * Elasticsearch Service Wrapper for STELA-2
 * Handles indexing, searching, and cluster setup with graceful fallback to MySQL.
 */

if (!defined('ELASTICSEARCH_HOST')) {
    define('ELASTICSEARCH_HOST', 'http://127.0.0.1:9200');
}
if (!defined('ELASTICSEARCH_ENABLED')) {
    define('ELASTICSEARCH_ENABLED', true);
}
if (!defined('ELASTICSEARCH_INDEX_PREFIX')) {
    define('ELASTICSEARCH_INDEX_PREFIX', 'stela_');
}

class ElasticsearchService {
    private static $instance = null;
    private $client = null;
    private $available = false;
    private $prefix;

    private function __construct() {
        $this->prefix = ELASTICSEARCH_INDEX_PREFIX;
        
        if (!ELASTICSEARCH_ENABLED) {
            $this->available = false;
            return;
        }

        if (!class_exists('\Elastic\Elasticsearch\ClientBuilder')) {
            $this->available = false;
            return;
        }

        try {
            $this->client = \Elastic\Elasticsearch\ClientBuilder::create()
                ->setHosts([ELASTICSEARCH_HOST])
                ->setRetries(0)
                ->build();

            // Fast ping with 1s timeout for instant MySQL fallback if offline
            $this->available = $this->client->ping([
                'client' => ['timeout' => 2, 'connect_timeout' => 1]
            ])->asBool();
        } catch (\Throwable $e) {
            $this->available = false;
            error_log('Elasticsearch Connection Warning: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isAvailable() {
        return $this->available;
    }

    public function getClient() {
        return $this->client;
    }

    public function getIndexName($type) {
        return $this->prefix . $type;
    }

    /**
     * Create default indices and field mappings if they don't exist
     */
    public function setupIndices() {
        if (!$this->isAvailable()) {
            return false;
        }

        $results = [];

        // Index Employees
        $employeeIndex = $this->getIndexName('employees');
        try {
            $exists = $this->client->indices()->exists(['index' => $employeeIndex])->asBool();
            if (!$exists) {
                $this->client->indices()->create([
                    'index' => $employeeIndex,
                    'body' => [
                        'settings' => [
                            'number_of_shards' => 1,
                            'number_of_replicas' => 0,
                            'analysis' => [
                                'analyzer' => [
                                    'stela_analyzer' => [
                                        'type' => 'custom',
                                        'tokenizer' => 'standard',
                                        'filter' => ['lowercase', 'asciifolding']
                                    ]
                                ]
                            ]
                        ],
                        'mappings' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'employee_code' => [
                                    'type' => 'keyword',
                                    'fields' => ['text' => ['type' => 'text']]
                                ],
                                'full_name' => [
                                    'type' => 'text',
                                    'analyzer' => 'stela_analyzer',
                                    'fields' => ['raw' => ['type' => 'keyword']]
                                ],
                                'position' => [
                                    'type' => 'text',
                                    'analyzer' => 'stela_analyzer',
                                    'fields' => ['raw' => ['type' => 'keyword']]
                                ],
                                'department' => [
                                    'type' => 'text',
                                    'analyzer' => 'stela_analyzer',
                                    'fields' => ['raw' => ['type' => 'keyword']]
                                ],
                                'contractor_company' => [
                                    'type' => 'text',
                                    'analyzer' => 'stela_analyzer',
                                    'fields' => ['raw' => ['type' => 'keyword']]
                                ],
                                'competency_type' => ['type' => 'keyword'],
                                'ruang_lingkup' => ['type' => 'text'],
                                'sub_competency' => ['type' => 'text'],
                                'supervision_area' => ['type' => 'text'],
                                'approval_status' => ['type' => 'keyword'],
                                'is_active' => ['type' => 'integer'],
                                'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||strict_date_optional_time']
                            ]
                        ]
                    ]
                ]);
                $results['employees'] = 'Created';
            } else {
                $results['employees'] = 'Already exists';
            }
        } catch (\Throwable $e) {
            $results['employees'] = 'Error: ' . $e->getMessage();
        }

        // Index Appointments
        $appointmentIndex = $this->getIndexName('appointments');
        try {
            $exists = $this->client->indices()->exists(['index' => $appointmentIndex])->asBool();
            if (!$exists) {
                $this->client->indices()->create([
                    'index' => $appointmentIndex,
                    'body' => [
                        'settings' => [
                            'number_of_shards' => 1,
                            'number_of_replicas' => 0
                        ],
                        'mappings' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'appointment_number' => [
                                    'type' => 'keyword',
                                    'fields' => ['text' => ['type' => 'text']]
                                ],
                                'employee_id' => ['type' => 'integer'],
                                'employee_name' => ['type' => 'text'],
                                'contractor_company' => ['type' => 'text'],
                                'competency_type' => ['type' => 'keyword'],
                                'status' => ['type' => 'keyword'],
                                'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||strict_date_optional_time']
                            ]
                        ]
                    ]
                ]);
                $results['appointments'] = 'Created';
            } else {
                $results['appointments'] = 'Already exists';
            }
        } catch (\Throwable $e) {
            $results['appointments'] = 'Error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Index or update single employee document
     */
    public function indexEmployee($data) {
        if (!$this->isAvailable() || empty($data['id'])) {
            return false;
        }

        try {
            $params = [
                'index' => $this->getIndexName('employees'),
                'id' => (string)$data['id'],
                'body' => [
                    'id' => (int)$data['id'],
                    'employee_code' => $data['employee_code'] ?? '',
                    'full_name' => $data['full_name'] ?? '',
                    'position' => $data['position'] ?? '',
                    'department' => $data['department'] ?? '',
                    'contractor_company' => $data['contractor_company'] ?? '',
                    'competency_type' => $data['competency_type'] ?? '',
                    'ruang_lingkup' => $data['ruang_lingkup'] ?? '',
                    'sub_competency' => $data['sub_competency'] ?? '',
                    'supervision_area' => $data['supervision_area'] ?? '',
                    'approval_status' => $data['approval_status'] ?? ($data['status'] ?? 'pending'),
                    'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                    'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ];
            return $this->client->index($params);
        } catch (\Throwable $e) {
            error_log('Elasticsearch indexEmployee error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete employee document from index
     */
    public function deleteEmployee($id) {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            return $this->client->delete([
                'index' => $this->getIndexName('employees'),
                'id' => (string)$id
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Index or update single appointment document
     */
    public function indexAppointment($data) {
        if (!$this->isAvailable() || empty($data['id'])) {
            return false;
        }

        try {
            $params = [
                'index' => $this->getIndexName('appointments'),
                'id' => (string)$data['id'],
                'body' => [
                    'id' => (int)$data['id'],
                    'appointment_number' => $data['appointment_number'] ?? '',
                    'employee_id' => (int)($data['employee_id'] ?? 0),
                    'employee_name' => $data['employee_name'] ?? ($data['full_name'] ?? ''),
                    'contractor_company' => $data['contractor_company'] ?? ($data['company'] ?? ''),
                    'competency_type' => $data['competency_type'] ?? '',
                    'status' => $data['status'] ?? 'draft',
                    'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ];
            return $this->client->index($params);
        } catch (\Throwable $e) {
            error_log('Elasticsearch indexAppointment error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete appointment document
     */
    public function deleteAppointment($id) {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            return $this->client->delete([
                'index' => $this->getIndexName('appointments'),
                'id' => (string)$id
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Search Employees in Elasticsearch with Fuzzy Match & Multi-match
     */
    public function searchEmployees($queryText = '', $filters = [], $from = 0, $size = 20) {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            $mustQueries = [];
            $filterQueries = [];

            if (!empty($queryText)) {
                $mustQueries[] = [
                    'multi_match' => [
                        'query' => $queryText,
                        'fields' => [
                            'employee_code^3',
                            'full_name^2',
                            'position',
                            'department',
                            'contractor_company^2',
                            'ruang_lingkup',
                            'sub_competency',
                            'supervision_area'
                        ],
                        'fuzziness' => 'AUTO',
                        'operator' => 'or'
                    ]
                ];
            } else {
                $mustQueries[] = ['match_all' => new \stdClass()];
            }

            if (!empty($filters['contractor_company'])) {
                $filterQueries[] = [
                    'term' => ['contractor_company.raw' => $filters['contractor_company']]
                ];
            }

            if (!empty($filters['competency_type'])) {
                $filterQueries[] = [
                    'term' => ['competency_type' => $filters['competency_type']]
                ];
            }

            if (!empty($filters['approval_status'])) {
                $filterQueries[] = [
                    'term' => ['approval_status' => $filters['approval_status']]
                ];
            }

            if (isset($filters['is_active'])) {
                $filterQueries[] = [
                    'term' => ['is_active' => (int)$filters['is_active']]
                ];
            }

            $body = [
                'from' => $from,
                'size' => $size,
                'query' => [
                    'bool' => [
                        'must' => $mustQueries,
                        'filter' => $filterQueries
                    ]
                ],
                'sort' => [
                    '_score' => ['order' => 'desc'],
                    'id' => ['order' => 'desc']
                ]
            ];

            $response = $this->client->search([
                'index' => $this->getIndexName('employees'),
                'body' => $body
            ]);

            $hits = $response['hits']['hits'] ?? [];
            $total = $response['hits']['total']['value'] ?? 0;

            $items = array_map(function($hit) {
                $source = $hit['_source'];
                $source['_score'] = $hit['_score'] ?? 0;
                return $source;
            }, $hits);

            return [
                'total' => $total,
                'items' => $items,
                'source' => 'elasticsearch'
            ];
        } catch (\Throwable $e) {
            error_log('Elasticsearch searchEmployees error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Search Appointments in Elasticsearch
     */
    public function searchAppointments($queryText = '', $filters = [], $from = 0, $size = 20) {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            $mustQueries = [];
            $filterQueries = [];

            if (!empty($queryText)) {
                $mustQueries[] = [
                    'multi_match' => [
                        'query' => $queryText,
                        'fields' => [
                            'appointment_number^3',
                            'employee_name^2',
                            'contractor_company'
                        ],
                        'fuzziness' => 'AUTO'
                    ]
                ];
            } else {
                $mustQueries[] = ['match_all' => new \stdClass()];
            }

            if (!empty($filters['status'])) {
                $filterQueries[] = [
                    'term' => ['status' => $filters['status']]
                ];
            }

            $body = [
                'from' => $from,
                'size' => $size,
                'query' => [
                    'bool' => [
                        'must' => $mustQueries,
                        'filter' => $filterQueries
                    ]
                ]
            ];

            $response = $this->client->search([
                'index' => $this->getIndexName('appointments'),
                'body' => $body
            ]);

            $hits = $response['hits']['hits'] ?? [];
            $total = $response['hits']['total']['value'] ?? 0;

            $items = array_map(function($hit) {
                return $hit['_source'];
            }, $hits);

            return [
                'total' => $total,
                'items' => $items,
                'source' => 'elasticsearch'
            ];
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reindex all employees from database to Elasticsearch
     */
    public function bulkIndexEmployees($dbConnection) {
        if (!$this->isAvailable()) {
            return ['success' => false, 'message' => 'Elasticsearch is not available'];
        }

        $this->setupIndices();

        $query = "SELECT * FROM employees";
        $result = $dbConnection->query($query);

        if (!$result) {
            return ['success' => false, 'message' => 'Failed to query employees table'];
        }

        $count = 0;
        $params = ['body' => []];

        while ($row = $result->fetch_assoc()) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->getIndexName('employees'),
                    '_id' => (string)$row['id']
                ]
            ];

            $params['body'][] = [
                'id' => (int)$row['id'],
                'employee_code' => $row['employee_code'] ?? '',
                'full_name' => $row['full_name'] ?? '',
                'position' => $row['position'] ?? '',
                'department' => $row['department'] ?? '',
                'contractor_company' => $row['contractor_company'] ?? '',
                'competency_type' => $row['competency_type'] ?? '',
                'ruang_lingkup' => $row['ruang_lingkup'] ?? '',
                'sub_competency' => $row['sub_competency'] ?? '',
                'supervision_area' => $row['supervision_area'] ?? '',
                'approval_status' => $row['approval_status'] ?? ($row['status'] ?? 'pending'),
                'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 1,
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
            ];

            $count++;

            if ($count % 500 === 0) {
                try {
                    $this->client->bulk($params);
                } catch (\Throwable $e) {
                    error_log("Bulk index error: " . $e->getMessage());
                    return ['success' => false, 'message' => 'Bulk error: ' . $e->getMessage()];
                }
                $params = ['body' => []];
            }
        }

        if (!empty($params['body'])) {
            try {
                $this->client->bulk($params);
            } catch (\Throwable $e) {
                error_log("Bulk index error: " . $e->getMessage());
                return ['success' => false, 'message' => 'Bulk error: ' . $e->getMessage()];
            }
        }

        return ['success' => true, 'count' => $count];
    }

    /**
     * Reindex all appointments from database to Elasticsearch
     */
    public function bulkIndexAppointments($dbConnection) {
        if (!$this->isAvailable()) {
            return ['success' => false, 'message' => 'Elasticsearch is not available'];
        }

        $this->setupIndices();

        $query = "SELECT a.*, e.full_name as employee_name, e.contractor_company 
                  FROM appointments a 
                  LEFT JOIN employees e ON a.employee_id = e.id";
        $result = $dbConnection->query($query);

        if (!$result) {
            return ['success' => false, 'message' => 'Failed to query appointments table'];
        }

        $count = 0;
        $params = ['body' => []];

        while ($row = $result->fetch_assoc()) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->getIndexName('appointments'),
                    '_id' => (string)$row['id']
                ]
            ];

            $params['body'][] = [
                'id' => (int)$row['id'],
                'appointment_number' => $row['appointment_number'] ?? '',
                'employee_id' => (int)($row['employee_id'] ?? 0),
                'employee_name' => $row['employee_name'] ?? '',
                'contractor_company' => $row['contractor_company'] ?? '',
                'competency_type' => $row['competency_type'] ?? '',
                'status' => $row['status'] ?? 'draft',
                'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
            ];

            $count++;

            if ($count % 500 === 0) {
                $this->client->bulk($params);
                $params = ['body' => []];
            }
        }

        if (!empty($params['body'])) {
            $this->client->bulk($params);
        }

        return ['success' => true, 'count' => $count];
    }
}
