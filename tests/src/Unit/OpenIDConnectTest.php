<?php

declare(strict_types = 1);

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openid_connect\OpenIDConnectAuthmap;
use Drupal\openid_connect\Plugin\OpenIDConnectClientInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\openid_connect\OpenIDConnect;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Class OpenIDConnectTest.
 */
class OpenIDConnectTest extends UnitTestCase {

  /**
   * Mock of the config factory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Mock of the OpenIDConnectAuthMap service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $authMap;

  /**
   * Mock of the entity_type.manager service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Mock of the entity field manager service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityFieldManager;

  /**
   * Mock of the account_proxy service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * Mock of the user data interface.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $userData;

  /**
   * Mock of the email validator.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $emailValidator;

  /**
   * Mock of the messenger service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * Mock of the module handler service.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * Mock of the logger interface.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The OpenIDConnect class being tested.
   *
   * @var \Drupal\openid_connect\OpenIDConnect
   */
  protected $openIdConnect;

  /**
   * Mock of the userStorageInterface.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $userStorage;

  /**
   * Mock of the open id connect logger.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $oidcLogger;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    require_once 'UserPasswordFixture.php';

    // Mock the config_factory service.
    $this->configFactory = $this
      ->createMock(ConfigFactoryInterface::class);

    // Mock the authMap open id connect service.
    $this->authMap = $this
      ->createMock(OpenIDConnectAuthmap::class);

    $this->userStorage = $this
      ->createMock(EntityStorageInterface::class);

    // Mock the entity type manager service.
    $this->entityTypeManager = $this
      ->createMock(EntityTypeManagerInterface::class);

    $this->entityTypeManager->expects($this->atLeastOnce())
      ->method('getStorage')
      ->with('user')
      ->willReturn($this->userStorage);

    $this->entityFieldManager = $this
      ->createMock(EntityFieldManagerInterface::class);

    $this->currentUser = $this
      ->createMock(AccountProxyInterface::class);

    $this->userData = $this
      ->createMock(UserDataInterface::class);

    $emailValidator = $this
      ->getMockBuilder('\Drupal\Component\Utility\EmailValidator')
      ->setMethods(NULL);
    $this->emailValidator = $emailValidator->getMock();

    $this->messenger = $this
      ->createMock(MessengerInterface::class);

    $this->moduleHandler = $this
      ->createMock(ModuleHandler::class);

    $this->logger = $this
      ->createMock(LoggerChannelFactoryInterface::class);

    $this->oidcLogger = $this
      ->createMock(LoggerChannelInterface::class);

    $this->logger->expects($this->atLeastOnce())
      ->method('get')
      ->with('openid_connect')
      ->willReturn($this->oidcLogger);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->openIdConnect = new OpenIDConnect(
      $this->configFactory,
      $this->authMap,
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->currentUser,
      $this->userData,
      $this->emailValidator,
      $this->messenger,
      $this->moduleHandler,
      $this->logger
    );
  }

  /**
   * Test for the userPropertiesIgnore method.
   */
  public function testUserPropertiesIgnore(): void {
    $defaultPropertiesIgnore = [
      'uid',
      'uuid',
      'langcode',
      'preferred_langcode',
      'preferred_admin_langcode',
      'name',
      'pass',
      'mail',
      'status',
      'created',
      'changed',
      'access',
      'login',
      'init',
      'roles',
      'default_langcode',
    ];
    $expectedResults = array_combine($defaultPropertiesIgnore, $defaultPropertiesIgnore);

    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with(
        'openid_connect_user_properties_ignore',
        $defaultPropertiesIgnore,
        []
      );

    $this->moduleHandler->expects($this->once())
      ->method('alterDeprecated')
      ->with(
        'hook_openid_connect_user_properties_to_skip_alter() is deprecated and will be removed in 8.x-1.x-rc1.', 'openid_connect_user_properties_to_skip',
        $defaultPropertiesIgnore
      );

    $actualPropertiesIgnored = $this->openIdConnect->userPropertiesIgnore([]);

    $this->assertArrayEquals($expectedResults, $actualPropertiesIgnored);
  }

  /**
   * Test the extractSub method.
   *
   * @param array $userData
   *   The user data as returned from
   *   OpenIDConnectClientInterface::decodeIdToken().
   * @param array $userInfo
   *   The user claims as returned from
   *   OpenIDConnectClientInterface::retrieveUserInfo().
   * @param bool|string $expected
   *   The expected result from the test.
   *
   * @dataProvider dataProviderForExtractSub
   */
  public function testExtractSub(
    array $userData,
    array $userInfo,
    $expected
  ): void {
    $actual = $this->openIdConnect->extractSub($userData, $userInfo);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Data provider for the testExtractSub method.
   *
   * @return array|array[]
   *   The array of tests for the method.
   */
  public function dataProviderForExtractSub(): array {
    $randomSub = $this->randomMachineName();
    return [
      [
        [],
        [],
        FALSE,
      ],
      [
        ['sub' => $randomSub],
        [],
        $randomSub,
      ],
      [
        [],
        ['sub' => $randomSub],
        $randomSub,
      ],
      [
        ['sub' => $this->randomMachineName()],
        ['sub' => $randomSub],
        FALSE,
      ],
    ];
  }

  /**
   * Test for the hasSetPassword method.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface|null $account
   *   The account to test or null if none provided.
   * @param bool $hasPermission
   *   Whether the account should have the correct permission
   *   to change their own password.
   * @param array $connectedAccounts
   *   The connected accounts array from the authMap method.
   * @param bool $expectedResult
   *   The result expected.
   *
   * @dataProvider dataProviderForHasSetPasswordAccess
   */
  public function testHasSetPasswordAccess(
    ?AccountProxyInterface $account,
    bool $hasPermission,
    array $connectedAccounts,
    bool $expectedResult
  ): void {
    if (empty($account)) {
      $this->currentUser->expects($this->once())
        ->method('hasPermission')
        ->with('openid connect set own password')
        ->willReturn($hasPermission);

      if (!$hasPermission) {
        $this->authMap->expects($this->once())
          ->method('getConnectedAccounts')
          ->with($this->currentUser)
          ->willReturn($connectedAccounts);
      }
    }
    else {
      $account->expects($this->once())
        ->method('hasPermission')
        ->with('openid connect set own password')
        ->willReturn($hasPermission);

      if (!$hasPermission) {
        $this->authMap->expects($this->once())
          ->method('getConnectedAccounts')
          ->with($account)
          ->willReturn($connectedAccounts);
      }
    }

    $actualResult = $this->openIdConnect->hasSetPasswordAccess($account);

    $this->assertEquals($expectedResult, $actualResult);
  }

  /**
   * Data provider for the testHasSetPasswordAccess method.
   *
   * @return array|array[]
   *   Data provider parameters for the testHasSetPassword() method.
   */
  public function dataProviderForHasSetPasswordAccess(): array {
    $connectedAccounts = [
      $this->randomMachineName() => 'sub',
    ];

    return [
      [
        $this->currentUser, FALSE, [], TRUE,
      ],
      [
        $this->currentUser, TRUE, [], TRUE,
      ],
      [
        NULL, TRUE, [], TRUE,
      ],
      [
        NULL, FALSE, [], TRUE,
      ],
      [
        $this->currentUser, FALSE, $connectedAccounts, FALSE,
      ],
      [
        $this->currentUser, TRUE, $connectedAccounts, TRUE,
      ],
      [
        NULL, TRUE, $connectedAccounts, TRUE,
      ],
      [
        NULL, FALSE, $connectedAccounts, FALSE,
      ],
    ];
  }

  /**
   * Test for the createUser method.
   *
   * @param string $sub
   *   The sub to use.
   * @param array $userinfo
   *   The userinfo array containing the email key.
   * @param string $client_name
   *   The client name for the user.
   * @param bool $status
   *   The user status.
   * @param bool $duplicate
   *   Whether to test a duplicate username.
   *
   * @dataProvider dataProviderForCreateUser
   */
  public function testCreateUser(
    string $sub,
    array $userinfo,
    string $client_name,
    bool $status,
    bool $duplicate
  ): void {
    // Mock the expected username.
    $expectedUserName = 'oidc_' . $client_name . '_' . md5($sub);

    // If the preferred username is defined, use it instead.
    if (array_key_exists('preferred_username', $userinfo)) {
      $expectedUserName = trim($userinfo['preferred_username']);
    }

    // If the name key exists, use it.
    if (array_key_exists('name', $userinfo)) {
      $expectedUserName = trim($userinfo['name']);
    }

    $expectedAccountArray = [
      'name' => ($duplicate ? "{$expectedUserName}_1" : $expectedUserName),
      'pass' => 'TestPassword123',
      'mail' => $userinfo['email'],
      'init' => $userinfo['email'],
      'status' => $status,
      'openid_connect_client' => $client_name,
      'openid_connect_sub' => $sub,
    ];

    // Mock the user account to be created.
    $account = $this
      ->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('save')
      ->willReturn(1);

    $this->userStorage->expects($this->once())
      ->method('create')
      ->with($expectedAccountArray)
      ->willReturn($account);

    if ($duplicate) {
      $this->userStorage->expects($this->exactly(2))
        ->method('loadByProperties')
        ->withConsecutive(
          [['name' => $expectedUserName]],
          [['name' => "{$expectedUserName}_1"]]
        )
        ->willReturnOnConsecutiveCalls([1], []);
    }
    else {
      $this->userStorage->expects($this->once())
        ->method('loadByProperties')
        ->with(['name' => $expectedUserName])
        ->willReturn([]);
    }

    $actualResult = $this->openIdConnect
      ->createUser($sub, $userinfo, $client_name, $status);

    $this->assertInstanceOf('\Drupal\user\UserInterface', $actualResult);
  }

  /**
   * Data provider for the testCreateUser method.
   *
   * @return array|array[]
   *   The parameters to pass to testCreateUser().
   */
  public function dataProviderForCreateUser(): array {
    return [
      [
        $this->randomMachineName(),
        ['email' => 'test@123.com'],
        '',
        FALSE,
        FALSE,
      ],
      [
        $this->randomMachineName(),
        [
          'email' => 'test@test123.com',
          'name' => $this->randomMachineName(),
        ],
        $this->randomMachineName(),
        TRUE,
        FALSE,
      ],
      [
        $this->randomMachineName(),
        [
          'email' => 'test@test456.com',
          'preferred_username' => $this->randomMachineName(),
        ],
        $this->randomMachineName(),
        TRUE,
        TRUE,
      ],
    ];
  }

  /**
   * Test coverate for the completeAuthorization() method.
   *
   * @param bool $authenticated
   *   Should the user be authenticated.
   * @param string $destination
   *   Destination string.
   * @param array $tokens
   *   Tokens array.
   * @param array $userData
   *   The user data array.
   * @param array $userInfo
   *   The user info array.
   * @param bool $preAuthorize
   *   Whether to preauthorize or not.
   * @param bool $accountExists
   *   Does the account already exist.
   *
   * @dataProvider dataProviderForCompleteAuthorization
   * @runInSeparateProcess
   */
  public function testCompleteAuthorization(
    bool $authenticated,
    string $destination,
    array $tokens,
    array $userData,
    array $userInfo,
    bool $preAuthorize,
    bool $accountExists
  ): void {

    $clientPluginId = $this->randomMachineName();

    $this->currentUser->expects($this->once())
      ->method('isAuthenticated')
      ->willReturn($authenticated);

    $client = $this
      ->createMock(OpenIDConnectClientInterface::class);

    $moduleHandlerCount = 1;

    if ($preAuthorize) {
      $moduleHandlerCount = 3;
    }

    if ($authenticated) {
      $this->expectException('RuntimeException');
    }
    else {
      $client->expects($this->once())
        ->method('decodeIdToken')
        ->with($tokens['id_token'])
        ->willReturn($userData);

      $client->expects($this->once())
        ->method('retrieveUserInfo')
        ->with($tokens['access_token'])
        ->willReturn($userInfo);

      $client->expects($this->any())
        ->method('getPluginId')
        ->willReturn($clientPluginId);

      if ($accountExists) {
        if (!$preAuthorize) {
          $moduleHandlerResults = [1, 2, FALSE];
        }
        else {
          $returnedAccount = $this
            ->createMock(UserInterface::class);

          if (!empty($userInfo['blocked'])) {
            $returnedAccount->expects($this->once())
              ->method('isBlocked')
              ->willReturn(TRUE);

            $this->messenger->expects($this->once())
              ->method('addError');
          }

          $moduleHandlerResults = [$returnedAccount];
        }

        $this->moduleHandler->expects($this->once())
          ->method('alter')
          ->with(
            'openid_connect_userinfo',
            $userInfo,
            [
              'tokens' => $tokens,
              'plugin_id' => $clientPluginId,
              'user_data' => $userData,
            ]
          );

        if (empty($userData) && empty($userInfo)) {

          $this->oidcLogger->expects($this->once())
            ->method('error')
            ->with(
              'No "sub" found from @provider (@code @error). Details: @details',
              ['@provider' => $clientPluginId]
            );
        }

        if (!empty($userInfo) && empty($userInfo['email'])) {

          $this->oidcLogger->expects($this->once())
            ->method('error')
            ->with(
              'No e-mail address provided by @provider (@code @error). Details: @details',
              ['@provider' => $clientPluginId]
            );
        }

        if (!empty($userInfo['sub'])) {
          $account = $this->createMock(UserInterface::class);
          $account->method('id')->willReturn(1234);
          $account->method('isNew')->willReturn(FALSE);

          $context = [
            'tokens' => $tokens,
            'plugin_id' => $clientPluginId,
            'user_data' => $userData,
            'userinfo' => $userInfo,
            'sub' => $userInfo['sub'],
          ];

          $this->authMap->expects($this->once())
            ->method('userLoadBySub')
            ->willReturn($account);

          $this->moduleHandler->expects($this->any())
            ->method('invokeAll')
            ->withConsecutive(
              ['openid_connect_pre_authorize'],
              ['openid_connect_userinfo_save'],
              ['openid_connect_post_authorize']
            )
            ->willReturnOnConsecutiveCalls(
              $moduleHandlerResults,
              TRUE,
              TRUE
            );

          if ($preAuthorize) {

            $this->entityFieldManager->expects($this->once())
              ->method('getFieldDefinitions')
              ->with('user', 'user')
              ->willReturn(['mail' => 'mail']);

            $immutableConfig = $this
              ->createMock(ImmutableConfig::class);

            $immutableConfig->expects($this->exactly(2))
              ->method('get')
              ->withConsecutive(
                ['always_save_userinfo'],
                ['userinfo_mappings']
              )
              ->willReturnOnConsecutiveCalls(
                TRUE,
                ['mail', 'name']
              );

            $this->configFactory->expects($this->exactly(2))
              ->method('get')
              ->with('openid_connect.settings')
              ->willReturn($immutableConfig);
          }
        }
      }
      else {
        $account = FALSE;
        $context = [
          'tokens' => $tokens,
          'plugin_id' => $clientPluginId,
          'user_data' => $userData,
          'userinfo' => $userInfo,
          'sub' => $userInfo['sub'],
        ];

        $this->authMap->expects($this->once())
          ->method('userLoadBySub')
          ->willReturn($account);

        $this->moduleHandler->expects($this->any())
          ->method('invokeAll')
          ->willReturnCallback(function (...$args) use ($account, $context) {
            $return = NULL;
            switch ($args[0]) {
              case 'openid_connect_pre_authorize':
                $return = [];
                break;

              default:
                $return = NULL;
                break;

            }
            return $return;
          });

        if ($userInfo['email'] === 'invalid') {
          $this->messenger->expects($this->once())
            ->method('addError');
        }
        else {
          if ($userInfo['email'] === 'duplicate@valid.com') {
            $account = $this
              ->createMock(UserInterface::class);

            $this->userStorage->expects($this->once())
              ->method('loadByProperties')
              ->with(['mail' => $userInfo['email']])
              ->willReturn([$account]);

            $immutableConfig = $this
              ->createMock(ImmutableConfig::class);

            $immutableConfig->expects($this->once())
              ->method('get')
              ->with('connect_existing_users')
              ->willReturn(FALSE);

            $this->configFactory->expects($this->once())
              ->method('get')
              ->with('openid_connect.settings')
              ->willReturn($immutableConfig);

            $this->messenger->expects($this->once())
              ->method('addError');
          }
          elseif ($userInfo['email'] === 'connect@valid.com') {
            $this->entityFieldManager->expects($this->any())
              ->method('getFieldDefinitions')
              ->with('user', 'user')
              ->willReturn(['mail' => 'mail']);

            $context = [
              'tokens' => $tokens,
              'plugin_id' => $clientPluginId,
              'user_data' => $userData,
            ];

            $this->moduleHandler->expects($this->once())
              ->method('alter')
              ->with(
                'openid_connect_userinfo',
                $userInfo,
                $context
              );

            if (isset($userInfo['newAccount']) && $userInfo['newAccount']) {
              $account = FALSE;
            }
            else {
              $account = $this
                ->createMock(UserInterface::class);

              if (isset($userInfo['blocked']) && $userInfo['blocked']) {
                $account->expects($this->once())
                  ->method('isBlocked')
                  ->willReturn(TRUE);

                if ($accountExists) {
                  var_dump($accountExists);
                  $this->messenger->expects($this->once())
                    ->method('addError');
                }
              }
            }

            if (isset($userInfo['newAccount']) && $userInfo['newAccount']) {
              $this->userStorage->expects($this->once())
                ->method('loadByProperties')
                ->with(['mail' => $userInfo['email']])
                ->willReturn(FALSE);
            }
            else {
              $this->userStorage->expects($this->once())
                ->method('loadByProperties')
                ->with(['mail' => $userInfo['email']])
                ->willReturn([$account]);
            }

            if (isset($userInfo['register'])) {
              switch ($userInfo['register']) {
                case 'admin_only':
                  if (empty($userInfo['registerOverride'])) {
                    $this->messenger->expects($this->once())
                      ->method('addError');
                  }
                  break;

                case 'visitors_admin_approval':
                  $this->messenger->expects($this->once())
                    ->method('addMessage');
                  break;

              }

            }

            $immutableConfig = $this
              ->createMock(ImmutableConfig::class);

            $immutableConfig->expects($this->any())
              ->method('get')
              ->willReturnCallback(function ($config) use ($userInfo) {
                $return = FALSE;

                switch ($config) {
                  case 'connect_existing_users':
                  case 'override_registration_settings':
                    if (empty($userInfo['registerOverride']) && isset($userInfo['newAccount']) && $userInfo['newAccount']) {
                      $return = FALSE;
                    }
                    else {
                      $return = TRUE;
                    }
                    break;

                  case 'register':
                    if (isset($userInfo['register'])) {
                      $return = $userInfo['register'];
                    }

                    break;

                  case 'userinfo_mappings':
                    $return = ['mail' => 'mail'];
                    break;
                }
                return $return;
              });

            $this->configFactory->expects($this->any())
              ->method('get')
              ->willReturnCallback(function ($config) use ($immutableConfig) {
                if (
                  $config === 'openid_connect.settings' ||
                  $config === 'user.settings'
                ) {
                  return $immutableConfig;
                }

                return FALSE;
              });
          }
        }
      }
    }

    $oidcMock = $this->getMockBuilder('\Drupal\openid_connect\OpenIDConnect')
      ->setConstructorArgs([
        $this->configFactory,
        $this->authMap,
        $this->entityTypeManager,
        $this->entityFieldManager,
        $this->currentUser,
        $this->userData,
        $this->emailValidator,
        $this->messenger,
        $this->moduleHandler,
        $this->logger,
      ])
      ->setMethods([
        'userPropertiesIgnore',
        'createUser',
      ])
      ->getMock();

    $oidcMock->method('userPropertiesIgnore')
      ->willReturn(['uid' => 'uid', 'name' => 'name']);

    $oidcMock->method('createUser')
      ->willReturn(
        $this->createMock(UserInterface::class)
      );

    $authorization = $oidcMock
      ->completeAuthorization($client, $tokens, $destination);

    if (empty($userData) && empty($userInfo)) {
      $this->assertEquals(FALSE, $authorization);
    }

    if (!empty($userInfo) && empty($userInfo['email'])) {
      $this->assertEquals(FALSE, $authorization);
    }
  }

  /**
   * Data provider for the testCompleteAuthorization() method.
   *
   * @return array|array[]
   *   Test parameters to pass to testCompleteAuthorization().
   */
  public function dataProviderForCompleteAuthorization(): array {
    $tokens = [
      "id_token" => $this->randomMachineName(),
      "access_token" => $this->randomMachineName(),
    ];

    return [
      [
        TRUE,
        '',
        [],
        [],
        [],
        FALSE,
        TRUE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [],
        FALSE,
        TRUE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => '',
        ],
        FALSE,
        TRUE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'test@test.com',
          'sub' => $this->randomMachineName(),
        ],
        FALSE,
        TRUE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'test@test.com',
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        TRUE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'invalid',
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'duplicate@valid.com',
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'connect@valid.com',
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'connect@valid.com',
          'blocked' => TRUE,
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'connect@valid.com',
          'blocked' => TRUE,
          'sub' => 'TESTING',
        ],
        TRUE,
        TRUE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'connect@valid.com',
          'newAccount' => TRUE,
          'register' => 'admin_only',
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'connect@valid.com',
          'newAccount' => TRUE,
          'register' => 'visitors',
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'connect@valid.com',
          'newAccount' => TRUE,
          'register' => 'visitors_admin_approval',
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
      [
        FALSE,
        '',
        $tokens,
        [],
        [
          'email' => 'connect@valid.com',
          'newAccount' => TRUE,
          'register' => 'admin_only',
          'registerOverride' => TRUE,
          'sub' => $this->randomMachineName(),
        ],
        TRUE,
        FALSE,
      ],
    ];
  }

}