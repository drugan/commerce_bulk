<?php

namespace Drupal\commerce_bulk\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Duplicate term.
 *
 * @Action(
 *   id = "commerce_bulk_term_duplicate",
 *   label = @Translation("Duplicate term"),
 *   type = "taxonomy_term"
 * )
 */
class TermDuplicate extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $messenger = \Drupal::messenger();
    $request = \Drupal::request();
    $storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
    if ($ids = explode('|', $request->query->get('ids'))) {
      $tree = $storage->loadTree($storage->load(end($ids))->bundle(), 0, NULL, TRUE);
      $names = '';
      $terms = $name_ids = [];
      // $dashed = $this->dashTerms('ru-RU', '','',1); || $dashed[$id] != $name
      foreach ($tree as $term) {
        $id = $term->id();
        $name = $term->getName();
        if (!in_array($id, $ids)) {
          $messenger->addError("The term $id=$name is wrong!!!!");
        }
        $name = "$id=" . str_repeat('-', $term->depth) . $name . PHP_EOL;
        $names .= $name;
        $terms[$id] = $term;
      }
      $values = [];
      $form_state->set('term', end($values));
      $form_state->set('terms', $terms);
      $readmehelp = readmehelp_converter_service();
      $path = $readmehelp->moduleHandler->getModuleDirectories()['commerce_bulk'] . '/commerce_bulk.module';
      $form['warning'] = [
        '#markup' => new TranslatableMarkup('<h2>Change and / or add terms for %count <span style="color:red">Name</span>s. New <em>Names</em> should be inserted after the last existing: one <em>Name</em> on each line with prepended with one dash "-" symbol for a child term, two dashes for a grandchild and so on.', ['%count' => count($terms)]),
      ];
      $form['names'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Names'),
        '#default_value' => $names,
        '#rows' => 20,
      ];
      $form['data_warning'] = [
        '#markup' => new TranslatableMarkup('<h3><mark>Tip:</mark> Optionally, you can pass <em>XML</em> or <em>JSON</em> data and alter each term value in the <mark>YOUR_MODULE_commerce_bulk_term_new_alter()</mark>. See example hook implementation in the commerce_bulk.module file:</h3><div style="border:1px solid grey">' . $readmehelp->highlightPhp($path, 74, 8) . '</div>'),
      ];
      $form['data'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Optional JSON or XML data'),
        '#rows' => 1,
      ];
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => 'CANCEL AND BACK',
        '#weight' => 1000,
      ];
      // Remove the "Action was applied to N items" message.
      $messenger->deleteByType('status');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] != 'edit-cancel') {
      $names = explode(PHP_EOL, trim($form_state->getValue('names')));
      $names = array_filter($names, function ($name) {
        return trim($name);
      });
      if ($names) {
        $module_handler = \Drupal::moduleHandler();
        $data = $form_state->get('data');
        $terms = $form_state->get('terms');
        $form_state->set('terms', NULL);
        $parents = [];
        $root = $prev = reset($terms);
        $parents[$root->depth] = $root->id();
        $weight = $root->getWeight();
        $timestamp = time();

        foreach ($names as $index => $name) {
          $parts = explode('=', $name);
          $tid = !isset($parts[2]) && isset($parts[1]) ? $parts[0] : (isset($parts[2]) ? trim($parts[2]) : 0);
          $name = isset($parts[1]) ? $parts[1] : $parts[0];
          if (($name = trim($name)) && ($len = strlen($name)) && ($name = ltrim($name, '-'))) {
            $parents = array_filter($parents);
            $parent = end($parents);
            if (isset($terms[$tid])) {
              $term = $terms[$tid];
              $weight = $term->getWeight();
            }
            else {
              $term = $prev->createDuplicate();
              if ($tid) {
                $term->set('tid', $tid);
              }
              $weight = !empty($terms[$parent]) ? $terms[$parent]->getWeight() : $weight;
              $term->setWeight(++$weight);
            }
            $depth = $len - strlen($name);
            $last_depth = array_flip($parents);
            $last_depth = end($last_depth);
            if (!$root) {
              $depth = ($depth < 1) ? 1 : $depth;
              $depth = ($depth - $last_depth) > 1 ? $last_depth + 1 : $depth;
              if ($depth > $last_depth) {
                $term->set('parent', $parents[$last_depth]);
              }
              elseif ($depth == $last_depth && !isset($parents[$depth - 1])) {
                $term->set('parent', $prev->id());
              }
              else {
                $term->set('parent', $parents[$depth - 1]);
              }
            }
            $term->setChangedTime($timestamp);
            $timestamp--;
            $module_handler->alter('commerce_bulk_term_new', $term, $name, $data);
            $term->setName($name)->save();
            if (!$root) {
              $parents[$depth] = $term->id();
            }
            $root = FALSE;
            $prev = $term;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $terms) {
    if ($terms) {
      $ids = [];
      foreach ($terms as $term) {
        $ids[] = $term->id();
      }
      $query = [
        'destination' => \Drupal::request()->getRequestUri(),
        'ids' => implode('|', $ids),
      ];
      $path = Url::fromUserInput('/admin/config/system/actions/configure/' . $this->getPluginId(), ['query' => $query])->toString();
      $response = new RedirectResponse($path);
      $response->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($term = NULL) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function access($term, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $term->access('create', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public static function dashTerms($file = '', $match = '', $engfile = '', $array = NULL) {
    $files = \Drupal::service('file_system');
    $file = $files->realpath("private://google/taxonomy-with-ids.{$file}.txt");
    if (file_exists($file)) {
      $file = file_get_contents($file);
    }
    if ($match) {
      $num = preg_match_all('/^\d+\ -\ ' . $match . '.*/m', $file, $matches);
      $file = $matches[0];
    }
    $eng = $files->realpath("private://google/taxonomy-with-ids.{$engfile}.txt");
    if ($engfile = file_exists($eng)) {
      $engfile = file_get_contents($eng);
      $engfile = explode(PHP_EOL, trim($engfile));
      $eng = [];
      foreach ($engfile as $line) {
        $line = explode(' - ', $line);
        $eng[$line[0]] = $line[1];
      }
    }
    $file = is_array($file) ? $file : explode(PHP_EOL, trim($file));
    $terms = $keyed = [];
    foreach ($file as $index => $line) {
      $parts = explode(' - ', $line);
      $dash = '';
      $names = explode(' > ', $parts[1]);
      foreach ($names as $term) {
        if (!isset($terms["{$dash}{$term}"])) {
          $engpart = $engfile ? "={$eng[$parts[0]]}" : '';
          $terms["{$dash}{$term}"] = "={$parts[0]}{$engpart}";
          $keyed[$parts[0]] = $term;
        }
        $dash .= '-';
      }
    }
    $file = '';
    foreach ($terms as $key => $value) {
      $file .= "={$key}{$value}" . PHP_EOL;
    }

    return $array ? $keyed : $file;
  }

}
