<?php

declare(strict_types=1);

namespace Drupal\pepsico_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides the configurable hero banner for the newsroom.
 */
#[Block(
  id: 'pepsico_newsroom_hero',
  admin_label: new TranslatableMarkup('Newsroom hero banner'),
  category: new TranslatableMarkup('PepsiCo'),
)]
final class NewsroomHeroBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'headline' => 'The PepsiCo Newsroom',
      'subheadline' => 'Stories about our brands, people and planet, all in one place.',
      'cta_label' => 'Browse latest news',
      'cta_url' => '/news',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['headline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Headline'),
      '#default_value' => $this->configuration['headline'],
      '#maxlength' => 120,
      '#required' => TRUE,
    ];
    $form['subheadline'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Subheadline'),
      '#default_value' => $this->configuration['subheadline'],
      '#rows' => 2,
    ];
    $form['cta_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $this->configuration['cta_label'],
      '#maxlength' => 60,
    ];
    $form['cta_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button path'),
      '#default_value' => $this->configuration['cta_url'],
      '#description' => $this->t('An internal path starting with a slash, for example /news.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $path = trim((string) $form_state->getValue('cta_url'));
    if ($path !== '' && !str_starts_with($path, '/')) {
      $form_state->setErrorByName('cta_url', $this->t('The button path must start with a slash.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    foreach (['headline', 'subheadline', 'cta_label', 'cta_url'] as $key) {
      $this->configuration[$key] = trim((string) $form_state->getValue($key));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $path = $this->configuration['cta_url'];

    return [
      '#type' => 'component',
      '#component' => 'pepsico_core:hero',
      '#props' => [
        'headline' => $this->configuration['headline'],
        'subheadline' => $this->configuration['subheadline'],
        'cta_label' => $this->configuration['cta_label'],
        'cta_url' => $path !== '' ? Url::fromUserInput($path)->toString() : '',
      ],
    ];
  }

}
