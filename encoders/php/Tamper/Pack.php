<?php namespace Tamper;

/**
 * @author      Bogdan Modzelewski <bogdan.modzelewski@procad.pl>
 * @copyright   (c) PROCAD SA
 * @link        https://github.com/motzel/tamper
 * @license     Apache 2.0 license
 */

/**
 * Pack interface
 *
 * @package Tamper
 */
interface Pack
{
  /**
   * Encode data with given implementation
   *
   * @param array $data associative array of data to encode
   * @param array $options encoder options
   *
   * @return array encoded structure
   */
  public function encode(array &$data, array $options);
}
