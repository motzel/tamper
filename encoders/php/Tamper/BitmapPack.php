<?php namespace Tamper;

/**
 * @author      Bogdan Modzelewski <bogdan.modzelewski@procad.pl>
 * @copyright   (c) PROCAD SA
 * @link        https://github.com/motzel/tamper
 * @license     Apache 2.0 license
 */

/**
 * Class BitmapPack
 *
 * Applicable to "tags" data @see https://github.com/NYTimes/tamper/wiki/Packs
 * Each bit of the item window represents a possibility
 *
 * @package Tamper
 */
class BitmapPack implements Pack
{
  /** @var string attribute name */
  protected $attrName;

  /** @var array array of possibilities */
  protected $possibilites;

  /** @var integer number of possible values for each item */
  protected $maxChoices;

  /**
   * @param string $attrName attribute name
   * @param array $possibilities array of possible string values
   * @param $maxChoices number of possible values for each item
   */
  function __construct($attrName, $possibilities, $maxChoices)
  {
    $this->attrName = $attrName;
    $this->possibilites = $possibilities;
    $this->maxChoices = $maxChoices;
  }

  /**
   * Encode "tags" data
   * One or multiple values for each item are supported
   *
   * @param array $data associative array of data to encode
   * @param array $options not used by this encoder
   *
   * @return array encoded structure
   */
  public function encode(array &$data, array $options = array())
  {
    $possibilities = array_flip($this->possibilites); // it's faster not to use array_search(), but just isset(), see below
    $bitWindowWidth = $itemWindowWidth = count($this->possibilites);

    $dataBits = $itemWindowWidth * count($data);
    $bytesFull = (int)($dataBits / 8);
    $remainingBits = $dataBits % 8;

    $bs = new BitSet();
    $bs->pushInt32($bytesFull);
    $bs->pushByte($remainingBits);

    foreach ($data as $item) {
      $bitNums = array();
      foreach ((array)$item[$this->attrName] as $val) {
        if(isset($possibilities[$val])) {
          $bitNums[$possibilities[$val]] = true;
        }
      }
      asort($bitNums, SORT_NUMERIC);

      foreach ($this->possibilites as $k => $v) {
        $bs->push(isset($bitNums[$k]) ? 1 : 0);
      }
    }

    return array(
      "attr_name" => $this->attrName,
      "encoding" => "bitmap",
      "possibilities" => $this->possibilites,
      "max_choices" => $this->maxChoices,
      "pack" => base64_encode($bs->getBytes()),
      "item_window_width" => $itemWindowWidth,
      "bit_window_width" => $bitWindowWidth,
    );
  }
}
