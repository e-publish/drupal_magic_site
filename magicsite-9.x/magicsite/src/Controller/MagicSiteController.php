<?php
namespace Drupal\magicsite\Controller;

use \Drupal\Core\Cache\CacheableMetadata;
use \Drupal\Core\Menu\MenuTreeParameters;

class MagicSiteController
{
  public function getContent($path, $page) {
    $content = '';
    $url = \Drupal::config('magicsite.settings')->get('magicsite_url');
    if(!strlen($url)) {
      \Drupal::messenger()->addError('Модуль интеграции с MagicSite не настроен.');
      return ['#markup' => ''];
    }
    if($page == 'list') {
      switch ($path) {
        case 'meals':
          $content  .= '<ul class="menu">';
          $content  .= '<li class="first leaf"><a href="/sveden/meals">Организация питания</a></li>';
          $content  .= '<li class="leaf"><a href="/food/index">Ежедневное меню горячего питания</a></li>';
          $content  .= '</ul>';
          break;
        default:
          $menu_tree = \Drupal::menuTree();
          $menu_name = 'magicsite';
          $parameters = new MenuTreeParameters();
          $tree = $menu_tree->load($menu_name, $parameters);
          $manipulators = array(
            array('callable' => 'menu.default_tree_manipulators:checkAccess'),
            array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
          );

          $subtree = $tree['magicsite.'.$path]->subtree;
          $subtree = $menu_tree->transform($subtree, $manipulators);

          $menu = $menu_tree->build($subtree);
          foreach ($menu['#items'] as &$item) {
//            $item['url']->setOption('fragment', 'block-aboutussubmenu');
          }
          $content .= \Drupal::service('renderer')->render($menu);
      }
    } else {
      $content .= '<script>var magicsite_url = "' . $url . '";</script>';
      $response = $this->getPage($url.$path.'/'.$page.'.html');
      if ($response['response_code'] == 200) {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response['response']);
        $xpath = new \DOMXPath($dom);

        $ls_ads = $xpath->query('//a');
        foreach ($ls_ads as $ad) {
          if ($ad->hasAttribute('href')) {
            $ad_url = $ad->getAttribute('href');
            $f = parse_url($ad_url);
            if (!isset($f['scheme']) && !isset($f['host']) && isset($f['path'])) {
              $ad->setAttribute('href', $url . $f['path']);
            }
          }
        }

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $image) {
          $src = $image->getAttribute('src');
          $f = parse_url($src);
          if (!isset($f['scheme']) && !isset($f['host']) && isset($f['path'])) {
            $image->setAttribute('src', $url . $f['path']);
          }
        }

        $categorys = $xpath->query("//*[contains(@class, 'inner-page-block')]");
        foreach ($categorys as $category) {
          $content .= $dom->saveHTML($category);
        }

        $links = $xpath->query("//link[contains(@id, 'magic-skin')]");
        $categorys = $xpath->query("//*[contains(@class, '" . $page . "-page-script')]");
        $magic_script ='';
        foreach ($categorys as $category) {
          $magic_script .= $dom->saveHTML($category);
        }
        libxml_clear_errors();
        $content = str_replace("\n", "", $content) . $magic_script;
      } else {
        $content = '<div>Не удалось получить данные. Удаленный сервер вернул код ' . ($response['response_code'] ?? '0') . '</div>';
      }
    }

    return [
      '#markup' => $content,
      '#allowed_tags' => [
        'script', 'iframe', 'fieldset', 'legend', 'div', 'span', 'dd', 'em', 'p', 'br', 'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tr', 'td', 'th', 'a', 'img', 'b', 'i','strong',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
      ],
      '#attached' => [
        'library' => ['magicsite/magicsite'],
      ]
    ];
  }

  public function getPage($uri, $header = FALSE) {
    $response = array();
    $url = parse_url($uri);
    $curlInit = curl_init($uri);

    curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT, 20);

    if ($header) {
      curl_setopt($curlInit, CURLOPT_HEADER, true);
      curl_setopt($curlInit, CURLOPT_NOBODY, true);
    }

    if ($url['scheme'] == 'https') {
      curl_setopt($curlInit, CURLOPT_SSL_VERIFYHOST	, 2);
    }

    curl_setopt($curlInit, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlInit, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curlInit, CURLOPT_COOKIEJAR, '-');
    curl_setopt($curlInit, CURLOPT_REFERER, $_SERVER['SERVER_NAME']);
    curl_setopt($curlInit, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");

    $response['response'] = curl_exec($curlInit);
    $response['effective_url'] = curl_getinfo($curlInit, CURLINFO_EFFECTIVE_URL);
    $response['response_code'] = intval(curl_getinfo($curlInit, CURLINFO_HTTP_CODE));

    curl_close($curlInit);

    return $response;
  }
}
