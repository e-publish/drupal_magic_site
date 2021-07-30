<?php
namespace Drupal\magicsite\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {
  protected function getEditableConfigNames() {
    return [
      'magicsite.settings',
    ];
  }

  public function getFormId() {
    return 'magicsite_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('magicsite.settings');

    $form['magicsite_url'] = [
      '#type' => 'textfield',
      '#title' => 'URL сайта в ИС MagicSite',
      '#default_value' => $config->get('magicsite_url'),
      '#description' => 'Укажите адрес сайта, созданного в виртуальном кабинете MagicSite (https://cp.edusite.ru)',
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (empty($form_state->getValue('magicsite_url'))) {
      $form_state->setErrorByName('magicsite_url', $this->t('This field is required.'));
    } elseif (!$this->getUrl($form_state->getValue('magicsite_url'))) {
      $form_state->setErrorByName('magicsite_url', 'Неверное значение поля "URL сайта в среде MagicSite"');
    } else {
      $form_state->setValue('magicsite_url', $this->getUrl($form_state->getValue('magicsite_url')));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('magicsite.settings')
      ->set('magicsite_url', $form_state->getValue('magicsite_url'))
      ->save();
  }

  private function getUrl($uri) {
    $uri = trim($uri);
    preg_match('/^(https?:\/\/)?/', $uri, $proto);
    if (!isset($proto[1])) {
      $uri = "http://" . $uri;
    }
    $url = parse_url($uri);
    if (!isset( $url['host'])) {
      return FALSE;
    }
    if (checkdnsrr($url['host'], "A") && gethostbyname($url['host']) == gethostbyname('edusite.ru')) {
      $output = $url['scheme'] . '://';
      $output .= $url['host'];
      if (isset($url['port'])) {
        $output .= ':' . $url['port'];
      }
      if (isset($url['path'])) {
        $output .= $url['path'];
      }
      $res = \Drupal\magicsite\Controller\MagicSiteController::getPage($output, 1);

      if ($res['response_code'] == 200) {
        return $res['effective_url'];
      }
    }

    return FALSE;
  }
}
