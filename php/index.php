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
    }

    public function clearData()
    {
        $this->cache->del(self::DATA_KEY);
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

    public function getCachedData(): string
    {
        if (!$this->cache->exists(self::DATA_KEY)) {
            $this->cache->set(self::DATA_KEY, json_encode($this->getData()), self::DATA_TTL);
        }

        if ($this->checkCacheTime(self::DATA_KEY)) {
            $this->cache->setEx(self::DATA_KEY, self::DATA_TTL, json_encode($this->getData()));
        }

        return $this->cache->get(self::DATA_KEY);
    }

    public function getCachedItem($id): string
    {
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

    public function getItemData($id)
    {
        header('Content-type: application/json');

        echo $this->getCachedItem($id);
    }

    public function getResponse()
    {
        header('Content-type: application/json');

        echo $this->getCachedData();
    }

    private function createRecords()
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->insertRecord();
        }
    }

    private function insertRecord()
    {
        try {
            $uuid = $this->genUuid();

            $query = $this->connection
                ->prepare("INSERT INTO data (uuid) VALUES (:uuid);");

            $query->bindParam('uuid', $uuid);
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

switch ($action) {
    case 'init':
        $server->initData();
        break;
    case 'clear':
        $server->clearData();
        break;
    case 'item':
        $server->getItemData($id);
        break;
    default:
        $server->getResponse();
}
