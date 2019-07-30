<?php

namespace Javanile\VtigerClient\Tests;

use Javanile\VtigerClient\VtigerClient as Client;
use PHPUnit\Framework\TestCase;
use PDO;

final class VtigerClientTest extends TestCase
{
    protected static $username;

    protected static $accessKey;

    protected static $endpoint;

    public static function setUpBeforeClass()
    {
        self::$endpoint = getenv('VT_ENDPOINT');
        self::$username = $username = getenv('VT_USERNAME');

        $mysqlHost = getenv('MYSQL_HOST');
        $mysqlDatabase = getenv('MYSQL_DATABASE');
        $mysqlRootPassword = getenv('MYSQL_ROOT_PASSWORD');
        $db = new PDO("mysql:host={$mysqlHost};dbname={$mysqlDatabase}", 'root', $mysqlRootPassword);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "SELECT accesskey FROM vtiger_users WHERE user_name='{$username}'";
        self::$accessKey = $db->query($sql)->fetchObject()->accesskey;
    }

    public function testGetChallengeWithoutUsername()
    {
        $expected = [
            'success' => false,
            'error'   => [
                'code'    => 'ACCESS_DENIED',
                'message' => 'No username given',
            ],
        ];

        $actual = (new Client([
            'endpoint' => self::$endpoint,
        ]))->getChallenge();

        $this->assertEquals($expected, $actual);
    }

    public function testGetChallengeSuccess()
    {
        $time = time();

        $client = new Client([
            'username'  => self::$username,
            'accessKey' => self::$accessKey,
            'endpoint'  => self::$endpoint,
        ]);

        $expected = [
            'success' => true,
            'result'  => [
                'token'      => null,
                'serverTime' => $time,
                'expireTime' => $time + 300,
            ],
        ];

        $actual = $client->getChallenge();

        $expected['result']['token'] = $actual['result']['token'];

        $this->assertEquals($expected['result']['token'], $client->getToken());
        $this->assertEquals($expected, $actual);
    }

    public function testLoginSuccess()
    {
        $client = new Client([
            'username'  => self::$username,
            'accessKey' => self::$accessKey,
            'endpoint'  => self::$endpoint,
        ]);

        $expected = [
            'success' => true,
            'result'  => [
                'sessionName'   => null,
                'userId'        => '19x1',
                'version'       => '0.22',
                'vtigerVersion' => null,
            ],
        ];

        $actual = $client->login();

        if (empty($actual['result']['sessionName'])) {
            var_dump($actual);
        }

        $expected['result']['sessionName'] = $actual['result']['sessionName'];
        $expected['result']['vtigerVersion'] = $actual['result']['vtigerVersion'];

        $this->assertEquals($expected['result']['sessionName'], $client->getSessionName());
        $this->assertEquals($expected, $actual);
    }

    public function testListTypes()
    {
        $client = new Client(self::$endpoint);
        $client->login(self::$username, self::$accessKey);

        $expected =  json_decode(file_get_contents(__DIR__.'/fixtures/listTypes.json'), true);

        $actual = $client->listTypes();
        #file_put_contents(__DIR__.'/fixtures/listTypes.json', json_encode($actual, JSON_PRETTY_PRINT));

        $this->assertEquals($expected, $actual);
    }

    public function testDescribe()
    {
        $client = new Client(self::$endpoint);
        $client->login(self::$username, self::$accessKey);

        $expected = json_decode(file_get_contents(__DIR__.'/fixtures/describeFaq.json'), true);

        $actual = $client->describe('Faq');
        #file_put_contents(__DIR__.'/fixtures/describeFaq.json', json_encode($actual, JSON_PRETTY_PRINT));

        $this->assertEquals($expected, $actual);
    }

    public function testCreate()
    {
        $client = new Client(self::$endpoint);
        $client->login(self::$username, self::$accessKey);

        $expected = json_decode(file_get_contents(__DIR__.'/fixtures/createFaq.json'), true);

        $actual = $client->create('Faq', [
            'faqstatus' => 'draft',
            'question' => 'this is a FAQ',
            'faq_answer' => 'this is a FAQ answer',
            'assigned_user_id' => 1,
        ]);
        file_put_contents(__DIR__.'/fixtures/createFaq.json', json_encode($actual, JSON_PRETTY_PRINT));

        $this->assertEquals($expected, $actual);
    }

    public function testListUsers()
    {
        $client = new Client(self::$endpoint);

        $client->login(self::$username, self::$accessKey);

        $expected = json_decode(file_get_contents(__DIR__.'/fixtures/listUsers.json'), true);

        $actual = $client->listUsers();
        #file_put_contents(__DIR__.'/fixtures/listUsers.json', json_encode($actual, JSON_PRETTY_PRINT));
        $expected['result'][0]['accesskey'] = $actual['result'][0]['accesskey'];

        $this->assertEquals($expected, $actual);
    }

    public function testUpload()
    {
        $client = new Client(self::$endpoint);

        $client->login(self::$username, self::$accessKey);

        $expected = [
            'success' => false,
            'error'   => [
                'code'    => 'EMPTY_RESPONSE',
                'message' => 'Web service send an empty body',
            ],
        ];

        $actual = $client->upload([
            'notes_title' => 'Sample Document',
            'assigned_user_id' => 1,
            'filelocationtype' => 'I',
            'filename' => __DIR__.'/fixtures/sampleDocument.pdf',
        ]);

        if (isset($actual['success']) && $actual['success']) {
            var_dump($actual);
        }

        $this->assertEquals($expected, $actual);
    }
}
