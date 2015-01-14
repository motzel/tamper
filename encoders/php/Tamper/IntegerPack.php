<?php namespace Tamper;

/**
 * @author      Bogdan Modzelewski <bogdan.modzelewski@procad.pl>
 * @copyright   (c) PROCAD SA
 * @link        https://github.com/motzel/tamper
 * @license     Apache 2.0 license
 */

/**
 * Class IntegerPack
 *
 * Applicable to "tags" data @see https://github.com/NYTimes/tamper/wiki/Packs
 * Each item is encoded as an index to possibilities array
 *
 * @package Tamper
 */
class IntegerPack implements Pack
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

    $bitWindowWidth = ceil(log(count($this->possibilites) + 1, 2));
    $itemWindowWidth = $bitWindowWidth * $this->maxChoices;

    $dataBits = $itemWindowWidth * count($data);
    $bytesFull = (int)($dataBits / 8);
    $remainingBits = $dataBits % 8;

    $bs = new BitSet();
    $bs->pushInt32($bytesFull);
    $bs->pushByte($remainingBits);

    foreach ($data as $item) {
      for ($i = 0; $i < $this->maxChoices; $i++) {
        $vals = (array)$item[$this->attrName];
        $idx = isset($vals[$i]) ? (isset($possibilities[$vals[$i]]) ? $possibilities[$vals[$i]] : false) : false;
        if ($idx === false) {
          $idx = 0;
        } else {
          $idx++;
        }

//        $bs->pushVal($idx, $bitWindowWidth);
        $mask = 1 << ($bitWindowWidth - 1);
        while($mask) {
          $bs->push($idx & $mask);
          $mask >>= 1;
        }
      }
    }

    return array(
      "attr_name" => $this->attrName,
      "encoding" => "integer",
      "possibilities" => $this->possibilites,
      "max_choices" => $this->maxChoices,
      "pack" => base64_encode($bs->getBytes()),
      "item_window_width" => $itemWindowWidth,
      "bit_window_width" => $bitWindowWidth,
    );
  }
}
