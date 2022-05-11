<?php

class Connection {
    private static array $connections = [
        'db' => null,
        'redis' => null
    ];

    protected function __construct() {}

    public static function getDBConnection(): PDO
    {
        if (!self::$connections['db']) {
            try {
                self::$connections['db'] = new PDO(
                    "mysql:host=mysql;dbname=db",
                    'admin',
                    'admin'
                );
                self::$connections['db']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (\Exception $exception) {
                echo "Connection failed: " . $exception->getMessage();
            }
        }

        return self::$connections['db'];
    }

    public static function getRedisConnection(): Redis
    {
        if (!self::$connections['redis']) {
            self::$connections['redis'] = new Redis();
            self::$connections['redis']->connect('redis', 6379);
        }

        return self::$connections['redis'];
    }
}

class Server {
    const DATA_KEY = 'data';
    const ITEM_KEY = 'item_';
    const DATA_TTL = 600;
    const MIN_TIME_PERCENT = 0.05; // 5%

    private array $uuids = [];

    public function __construct(
        private PDO $connection,
        private Redis $cache
    ) {
    }

    public function initData()
    {
        $this->dropTable();
        $this->createTable();
        $this->createRecords();
        $this->clearCache();

        echo 'Init done' . PHP_EOL;
    }

    public function clearCache()
    {
        $this->cache->flushAll();

        echo 'Clear cache done' . PHP_EOL;
    }

    public function getData(): array
    {
        $query = $this->connection->prepare("SELECT * FROM data LIMIT 100 OFFSET 500;");
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItem($id): array
    {
        $query = $this->connection->prepare("SELECT * FROM data WHERE id = $id;");
        $query->execute();

        return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function getCachedData(bool $useCache = false): string
    {
        if ($useCache === false) {
            return json_encode($this->getData());
        }

        if (!$this->cache->exists(self::DATA_KEY)) {
            $this->cache->set(self::DATA_KEY, json_encode($this->getData()), self::DATA_TTL);
        }

        if ($this->checkCacheTime(self::DATA_KEY)) {
            $this->cache->setEx(self::DATA_KEY, self::DATA_TTL, json_encode($this->getData()));
        }

        return $this->cache->get(self::DATA_KEY);
    }

    public function getCachedItem($id, bool $useCache = false): string
    {
        if ($useCache === false) {
            return json_encode($this->getItem($id));
        }

        if (!$this->cache->exists(self::ITEM_KEY . $id)) {
            $this->cache->set(self::ITEM_KEY . $id, json_encode($this->getItem($id)), self::DATA_TTL);
        }

        if ($this->checkCacheTime(self::ITEM_KEY . $id)) {
            $this->cache->setEx(self::ITEM_KEY . $id, self::DATA_TTL, json_encode($this->getItem($id)));
        }

        return $this->cache->get(self::ITEM_KEY . $id);
    }

    public function checkCacheTime(string $key): bool
    {
        $time = $this->cache->ttl($key);
        $minTime = $time * self::MIN_TIME_PERCENT;

        if ($time > $minTime) {
            return false;
        }

        return rand(0, $time) === 0;
    }

    public function getItemData($id, bool $useCache = false)
    {
        try {
            header('Content-type: application/json');

            echo $this->getCachedItem($id, $useCache);
        } catch (\Exception $exception) {
            header('Content-type: application/json');
            header('HTTP/1.1 500 Internal Server Error');
        }
    }

    public function getResponse(bool $useCache = false)
    {
        try {
            header('Content-type: application/json');

            echo $this->getCachedData($useCache);
        } catch (\Exception $exception) {
            header('Content-type: application/json');
            header('HTTP/1.1 500 Internal Server Error');
        }
    }

    private function createRecords()
    {
        for ($i = 1; $i <= 1000000; $i++) {
            $this->uuids[] = $this->genUuid();

            if ($i % 1000 === 0) {
                $this->insertRecords();

                $this->uuids = [];
            }
        }
    }

    private function insertRecords()
    {
        try {
            $sql = sprintf(
                "INSERT INTO data (uuid) VALUES ('%s');",
                implode("'), ('", $this->uuids)
            );

            $query = $this->connection->prepare($sql);

            $query->execute();
        } catch (\Exception $exception) {
            echo "SQL error: " . $exception->getMessage() . PHP_EOL;
        }
    }

    private function dropTable()
    {
        try {
            $this->connection
                ->prepare("DROP TABLE IF EXISTS data;")
                ->execute();
        } catch (\Exception $exception) {
            echo "SQL error: " . $exception->getMessage() . PHP_EOL;
        }
    }

    private function createTable()
    {
        try {
            $this->connection
                ->prepare("CREATE TABLE IF NOT EXISTS data (
                    id int auto_increment,
                    uuid varchar(36) not null,
                    constraint data_pk primary key (id)
                );")
                ->execute();
        } catch (\Exception $exception) {
            echo "SQL error: " . $exception->getMessage() . PHP_EOL;
        }
    }

    private function genUuid(): string {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}

$server = new Server(
    Connection::getDBConnection(),
    Connection::getRedisConnection()
);

$action = $_GET['action'] ?? 'default';
$id = $_GET['id'] ?? null;
$useCache = isset($_GET['cache']) ?? false;

switch ($action) {
    case 'init':
        $server->initData();
        break;
    case 'clear':
        $server->clearCache();
        break;
    case 'item':
        $server->getItemData($id, $useCache);
        break;
    default:
        $server->getResponse($useCache);
}
