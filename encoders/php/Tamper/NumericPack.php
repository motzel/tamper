<?php namespace Tamper;

/**
 * @author      Bogdan Modzelewski <bogdan.modzelewski@procad.pl>
 * @copyright   (c) PROCAD SA
 * @link        https://github.com/motzel/tamper
 * @license     Apache 2.0 license
 */

/**
 * Class NumericPack
 *
 * Applicable to numeric data
 *
 * @package Tamper
 */
class NumericPack implements Pack
{
  /** @var attribute name  */
  protected $attrName;

  protected $possibilites;
  protected $maxChoices;

  /**
   * @param string $attrName attribute name
   * @param null $possibilities not used by this encoder
   * @param null $maxChoices not used by this encoder
   */
  function __construct($attrName, $possibilities = null, $maxChoices = null)
  {
    $this->attrName = $attrName;
    $this->possibilites = $possibilities;
    $this->maxChoices = $maxChoices;
  }

  /**
   * Encode numeric data
   * Integers & floats with given precision are supported
   *
   * @param array $data associative array of data to encode
   * @param array $options encoder options
   *    - integer precision number of significant digits after the decimal, default 0 (integer precision)
   *    - integer/float mininimum value of the data, default 0
   *    - integer/float maximum value of the data, no default/required
   *
   * @return array encoded structure
   *
   * @throws \InvalidArgumentException
   */
  public function encode(array &$data, array $options = array())
  {
    if(!isset($options["max"]))
      throw new \InvalidArgumentException("'max' option for NumericPack is required");

    $precision = isset($options["precision"]) ? $options["precision"] : 0;
    $multiplier = pow(10, $precision);
    $min = isset($options["min"]) ? $options["min"] : 0;
    $max = $options["max"];
    $delta = (int)round($min * $multiplier);
    $maxMultiplied = (int)round($max * $multiplier) - $delta + 1;

    $itemWindowWidth = ceil(log($maxMultiplied + 1, 2));

    $dataBits = $itemWindowWidth * count($data);
    $bytesFull = (int)($dataBits / 8);
    $remainingBits = $dataBits % 8;

    $bs = new BitSet();
    $bs->pushInt32($bytesFull);
    $bs->pushByte($remainingBits);

    foreach($data as $item) {
      if(isset($item[$this->attrName])) {
        $val = (int)round($item[$this->attrName] * $multiplier) - $delta + 1;
      } else {
        $val = 0;
      }

//      $bs->pushVal($val, $itemWindowWidth);
      $mask = 1 << ($itemWindowWidth - 1);
      while($mask) {
        $bs->push($val & $mask);
        $mask >>= 1;
      }
    }

    return array(
      "attr_name" => $this->attrName,
      "encoding" => "numeric",
      "pack" => base64_encode($bs->getBytes()),
      "item_window_width" => $itemWindowWidth,
      "precision" => $precision,
      "delta" => $delta,
      "min" => $min,
      "max" => $max
    );
  }
}
