<?php

namespace Drupal\openid_connect\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientInterface;
use Drupal\openid_connect\Plugin\OpenIDConnectClientCollection;

/**
 * Defines the OpenID Connect client entity.
 *
 * @ConfigEntityType(
 *   id = "openid_connect_client",
 *   label = @Translation("OpenID Connect client"),
 *   admin_permission = "administer openid connect clients",
 *   handlers = {
 *     "list_builder" = "Drupal\openid_connect\Controller\OpenIDConnectClientListBuilder",
 *     "form" = {
 *       "add" = "Drupal\openid_connect\Form\OpenIDConnectClientAddForm",
 *       "edit" = "Drupal\openid_connect\Form\OpenIDConnectClientEditForm",
 *       "delete" = "Drupal\openid_connect\Form\OpenIDConnectClientDeleteForm",
 *     }
 *   },
 *   config_prefix = "client",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/services/openid-connect/{openid_connect_client}/edit",
 *     "delete-form" = "/admin/config/services/openid-connect/{openid_connect_client}/delete",
 *     "enable" = "/admin/config/services/openid-connect/{openid_connect_client}/enable",
 *     "disable" = "/admin/config/services/openid-connect/{openid_connect_client}/disable",
 *     "collection" = "/admin/config/services/openid-connect",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "settings",
 *   }
 * )
 */
class OpenIDConnectClientEntity extends ConfigEntityBase implements OpenIDConnectClientEntityInterface {

  /**
   * The OpenID Connect client ID.
   *
   * @var string
   */
  public $id;

  /**
   * The OpenID Connect client label.
   *
   * @var string
   */
  public $label;

  /**
   * The plugin instance ID.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The plugin instance settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The OpenID Connect plugin manager.
   *
   * @var \Drupal\openid_connect\Plugin\OpenIDConnectClientManager
   */
  protected $pluginManager;

  /**
   * The plugin collection that holds the openid_connect_client for this entity.
   *
   * @var \Drupal\openid_connect\Plugin\OpenIDConnectClientCollection
   */
  protected $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->pluginManager = \Drupal::service('plugin.manager.openid_connect_client');
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() : OpenIDConnectClientInterface {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->plugin;
  }

  /**
   * Encapsulates creation of the OpenID Connect client's LazyPluginCollection.
   *
   * @return \Drupal\openid_connect\Plugin\OpenIDConnectClientCollection
   *   The OpenID Connect client plugin collection.
   */
  protected function getPluginCollection(): OpenIDConnectClientCollection {
    if (!$this->pluginCollection) {
      $this->pluginCollection = new OpenIDConnectClientCollection($this->pluginManager, $this->plugin, $this->get('settings'), $this->id());
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return ['settings' => $this->getPluginCollection()];
  }

}
