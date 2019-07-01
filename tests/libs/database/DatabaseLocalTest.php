<?php
use PHPUnit\Framework\TestCase;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Database\Exception\DatabaseErrorException;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;

/**
 * Class DatabaseLocalTest
 * @group unit
 */
class DatabaseLocalTest extends TestCase
{
    private static $pdo;
    const TEST_DB = 'test_tiki_db';
    const TEST_PASS = 'test_tiki_pass';
    const TEST_PREFIX = 'test_tiki';
    const TEST_USER = 'test_tiki_user';

    public function getConnection()
    {
        if (!self::$pdo) {
            $dsn = sprintf('mysql:host=%s;charset=utf8', $_ENV['DB_HOST']);
            self::$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
        }
        return self::$pdo;
    }

    public function getInstance()
    {
        $instance = $this->createMock(Instance::class);
        $access = new Local($instance);
        $instance->method('getBestAccess')
             ->willReturn($access);
        $instance->method('getExtensions')
             ->willReturn(array());
        return $instance;
    }

    public function getDBInstance()
    {
        $instance = $this->getInstance();
        $db = new Database($instance);
        $db->host = $_ENV['DB_HOST'];
        $db->user = $_ENV['DB_USER'];
        $db->pass = $_ENV['DB_PASS'];
        $db->dbname = 'tiki_db';
        $db->connect();
        return $db;
    }

    public function getTikiConfig($user, $pass, $host, $db)
    {
        return '<?php $db_tiki = "mysqli";'
            . sprintf('$user_tiki = "%s";', $user)
            . sprintf('$pass_tiki = "%s";', $pass)
            . sprintf('$host_tiki = "%s";', $host)
            . sprintf('$dbs_tiki = "%s";', $db);
    }

    public function tearDown()
    {
        $pdo = $this->getConnection();
        $pdo->query(sprintf('DROP USER "%s"@"%s"', self::TEST_USER, '%'));
        $pdo->query(sprintf('DROP DATABASE `%s`', self::TEST_DB));
    }

    public function testCreateInstanceTikiConfig()
    {
        $instance = $this->getInstance();
        $configData = $this->getTikiConfig($_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_HOST'], 'tiki_db');
        $configFile = '/tmp/testCreateInstanceTikiConfig.php';
        file_put_contents($configFile, $configData);

        $db = Database::createFromConfig($instance, $configFile);
        $this->assertNotNull($db);
    }

    public function testSimpleQuery()
    {
        $db = $this->getDBInstance();
        $sql = 'SELECT 1;';
        $output = trim($db->query($sql));
        $this->assertEquals('1', $output);
    }

    public function testGetMaxUsernameLength()
    {
        $pdo = $this->getConnection();
        $db = $this->getDBInstance();

        $expected = $pdo->query(
            'SELECT CHARACTER_MAXIMUM_LENGTH'
            . ' FROM information_schema.COLUMNS'
            . ' WHERE TABLE_NAME="user"'
            .   ' AND TABLE_SCHEMA="mysql"'
            .   ' AND COLUMN_NAME="User"'
        );
        $expected = (int) $expected->fetchColumn();

        $result = $db->getMaxUsernameLength();
        $this->assertEquals($expected, $result);
    }

    public function testEmptyCredentialsFailConnectionTest()
    {
        $db = $this->getDBInstance();
        $db->host = null;
        $db->user = null;
        $db->pass = null;

        $this->assertFalse(false, $db->testConnection());
    }

    public function testEmptyCredentialsRaisesExceptionOnConnect()
    {
        $db = $this->getDBInstance();
        $db->host = null;
        $db->user = null;
        $db->pass = null;
        $hasError = false;

        try {
            $db->connect();
        } catch (Exception $e) {
            $hasError = true;
            $this->assertInstanceOf(DatabaseErrorException::class, $e);
        }

        $this->assertTrue($hasError);
    }

    public function testCreateDatabase()
    {
        $pdo = $this->getConnection();
        $db = $this->getDBInstance();

        $db->createDatabase(self::TEST_DB);

        $expected = $pdo->query(sprintf(
            'SHOW DATABASES LIKE "%s"',
            self::TEST_DB
        ));
        $expected = $expected->fetchColumn();
        $this->assertEquals($expected, self::TEST_DB);
    }

    public function testCreateUser()
    {
        $pdo = $this->getConnection();
        $db = $this->getDBInstance();

        $db->createUser(self::TEST_USER, self::TEST_PASS);

        $result = $pdo->query(sprintf(
            'SELECT * FROM mysql.user WHERE user="%s" AND password=PASSWORD("%s")',
            self::TEST_USER,
            self::TEST_PASS
        ));
        $result = $result->rowCount();
        $this->assertEquals(1, $result);
    }

    public function testGrantRights()
    {
        $pdo = $this->getConnection();
        $db = $this->getDBInstance();

        $db->createUser(self::TEST_USER, self::TEST_PASS);
        $db->createDatabase(self::TEST_DB);
        $db->grantRights(self::TEST_USER, self::TEST_DB);

        $host = $db->host === 'localhost' ? $this->host : '%';
        $result = $pdo->query(sprintf(
            'SHOW GRANTS FOR "%s"@"%s"',
            self::TEST_USER,
            $host
        ));
        $this->assertNotEmpty($result);
        $result = array_map('end', $result->fetchAll());
        $result = join("\n", $result);

        $expected = sprintf(
            "/GRANT ALL PRIVILEGES ON .%s.\.\* TO '%s'@'%s'/",
            self::TEST_DB,
            self::TEST_USER,
            $host
        );

        $this->assertRegExp($expected, $result);
    }

    public function testCreateAccess()
    {
        $pdo = $this->getConnection();
        $dbRoot = $this->getDBInstance();
        $dbUser = $dbRoot->createAccess(self::TEST_USER, self::TEST_DB);

        $this->assertNotEmpty($dbUser);
        $this->assertTrue($dbUser->testConnection());
    }
}
