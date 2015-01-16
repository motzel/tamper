<?php namespace Tamper;

/**
 * @author      Bogdan Modzelewski <bogdan.modzelewski@procad.pl>
 * @copyright   (c) PROCAD SA
 * @link        https://github.com/motzel/tamper
 * @license     Apache 2.0 license
 */

/**
 * Class Tamper
 *
 * PHP implementation of Tamper serialization protocol
 * @see https://github.com/NYTimes/tamper
 *
 * In original implementation only IntegerPack and BitmapPack categorical data were implemented.
 * PHP version adds new NumericPack for numeric data encoding.
 *
 * Javascript decoder for NumericPack is also available
 * @see https://github.com/motzel/tamper
 *
 * Example usage:
 *
 * $tamp = new Tamper();
 * $data = array(
 *    array("guid" => 1, "int"=>1, "float"=>0.5, "float2"=>5.351, "category"=>"fist"),
 *    array("guid" => 2, "int"=>4, "float"=>1.3, "float2"=>15.862, "category"=>"second"),
 *    array("guid" => 5, "int"=>8, "float"=>2.8, "float2"=>8.458, "category"=>"fist"),
 * );
 * echo json_encode($tamp->pack($data, array("float"=>1, "float2"=>3)));
 *
 * @package Tamper
 */
class Tamper
{
  /**
   * Encode data
   *
   * @param array $arr data to encode
   * @param array $numericTagsPrecision array for numeric data precision, array should contains name=>precision items
   * for each numeric field (0 if not set)
   *
   * @return array encoded data
   */
  public function pack(array &$arr, array $numericTagsPrecision = array())
  {
    $ret = array(
      "version" => "2.1",
      "existence" => array(
        "encoding" => "existence",
        "pack" => $this->packExistence(
          array_map(
            function ($item) {
              return $item["guid"];
            },
            $arr
          )
        )
      )
    );

    // check possible attribute values
    $attrs = array();
    foreach ($arr as $item) {
      foreach ($item as $k => $v) {
        if ($k == "guid") {
          continue;
        }

        if (is_string($v)) {
          if (!isset($attrs[$k])) {
            $attrs[$k] = array("type" => "tags", "options"=>array(), "maxChoices" => 1, "possibilities" => array());
          }

          if(!isset($attrs[$k]["possibilities"][$v])) {
            $attrs[$k]["possibilities"][$v] = $v;
          }
        } elseif(is_array($v)) {
          if (!isset($attrs[$k])) {
            $attrs[$k] = array("type" => "tags", "options"=>array(), "maxChoices" => 0, "possibilities" => array());
          }

          $cnt = count($v);
          if ($cnt > $attrs[$k]["maxChoices"]) {
            $attrs[$k]["maxChoices"] = $cnt;
          }

          foreach ($v as $choice) {
            if(!isset($attrs[$k]["possibilities"][$choice])) {
              $attrs[$k]["possibilities"][$choice] = $choice;
            }
          }
        } elseif (is_int($v)) {
          if (!isset($attrs[$k])) {
            $attrs[$k] = array("type" => "numeric", "options"=>array("precision" => 0, "min" => null, "max" => null));
          }

          if (is_null($attrs[$k]["options"]["min"]) || $attrs[$k]["options"]["min"] > $v) {
            $attrs[$k]["options"]["min"] = $v;
          }

          if (is_null($attrs[$k]["options"]["max"]) || $attrs[$k]["options"]["max"] < $v) {
            $attrs[$k]["options"]["max"] = $v;
          }
        } elseif (is_double($v)) {
          if (!isset($attrs[$k])) {
            $attrs[$k] = array("type" => "numeric", "options"=>array("precision" => isset($numericTagsPrecision[$k]) ? $numericTagsPrecision[$k] : 0, "min" => null, "max" => null));
          }

          if (is_null($attrs[$k]["options"]["min"]) || $attrs[$k]["options"]["min"] > $v) {
            $attrs[$k]["options"]["min"] = $v;
          }

          if (is_null($attrs[$k]["options"]["max"]) || $attrs[$k]["options"]["max"] < $v) {
            $attrs[$k]["options"]["max"] = $v;
          }
        }
      }
    }

    // choose right packer for each attribute
    foreach ($attrs as $ka => $attr) {
      switch ($attr["type"]) {
        case "tags":
          $cnt = count($attr["possibilities"]);
          if ($attr["maxChoices"] * log($cnt + 1, 2) < $cnt) {
            $encoding = "integer";
          } else {
            $encoding = "bitmap";
          }

          $encoder = $encoding == "integer"
            ? new IntegerPack($ka, array_values($attr["possibilities"]), $attr["maxChoices"])
            : new BitmapPack($ka, array_values($attr["possibilities"]), $attr["maxChoices"]);

          $ret["attributes"][] = $encoder->encode($arr, $attr["options"]);
          break;

        case "numeric":
          $encoder = new NumericPack($ka);
          $ret["attributes"][] = $encoder->encode($arr, $attr["options"]);
          break;
      }
    }

    return $ret;
  }

  /**
   * Encode GUIDs using Existence pack method
   * @see https://github.com/NYTimes/tamper/wiki/Packs
   *
   * @param array $arr array of guids, has to be sorted
   * @return string Base64 encoded string
   */
  public function packExistence(array $arr)
  {
    $encodingSet = array();
    $currentChunk = array();
    $lastGuid = -1;
    $runCounter = 0;

    foreach ($arr as $guid) {
      $diff = $guid - $lastGuid;

      if ($diff == 1) {
        // guid is one step forward
        $currentChunk[] = 1;
        $runCounter++;
      } elseif ($diff > 40) {
        // skip block is only space-efficient if the skip is greater than 40
        $this->dumpChunk($currentChunk, $runCounter, $encodingSet);

        $this->addEncodingBlock(
          $encodingSet,
          array(
            "type" => "skip",
            "length" => $diff - 1
          )
        );

        $currentChunk = array(1);
        $runCounter = 1;
      } else {
        // skips < 40 should be encoded as bitmap 0s
        if ($runCounter > 40) {
          $this->dumpChunk($currentChunk, $runCounter, $encodingSet);
          $currentChunk = array();
          $runCounter = 0;
        }

        for ($i = 0; $i < $diff - 1; $i++) {
          $currentChunk[] = 0;
        }
        $currentChunk[] = 1;
        $runCounter = 1;
      }

      $lastGuid = $guid;
    }

    $this->dumpChunk($currentChunk, $runCounter, $encodingSet);

    return $this->encodeSet($encodingSet);
  }

  protected function encodeSet(array $encodingSet)
  {
    $out = "";

    foreach ($encodingSet as $chunk) {
      switch ($chunk["type"]) {
        case "bitmap":
          $out .= chr(0); // keep control code 00000000
          $bits = $chunk["data"]->getSize();
          $fullBytes = (int)($bits / 8);
          $out .= implode($this->getInt32Bytes($fullBytes)); // number of full bytes to include
          $out .= chr($bits % 8); // number of remainder bits
          $out .= $chunk["data"]->getBytes(); // bitmap data
          break;

        case "skip":
          $out .= chr(1); // skip control code 00000001
          $out .= implode($this->getInt32Bytes($chunk["length"]));
          break;

        case "run":
          $out .= chr(2); // run control code 00000010
          $out .= implode($this->getInt32Bytes($chunk["length"]));
          break;
      }
    }

    return base64_encode($out);
  }

  protected function decodeSet(array $encodingSet)
  {
    $ret = array();
    $guid = 0;
    foreach ($encodingSet as $chunk) {
      switch ($chunk["type"]) {
        case "bitmap":
          foreach ($chunk["data"] as $bit) {
            if ($bit) {
              $ret[] = $guid;
            }

            $guid++;
          }
          break;

        case "skip":
          $guid += $chunk["length"];
          break;

        case "run":
          for ($i = 0; $i < $chunk["length"]; $i++) {
            $ret[] = $guid;
            $guid++;
          }
          break;
      }
    }

    return $ret;
  }

  protected function dumpChunk(array $currentChunk, $runCounter, array &$encodingSet)
  {
    if ($runCounter > 40) {
      $this->dumpChunk(array_slice($currentChunk, 0, count($currentChunk) - $runCounter), 0, $encodingSet);
      $this->addEncodingBlock(
        $encodingSet,
        array(
          "type" => "run",
          "length" => $runCounter
        )
      );
    } elseif (!empty($currentChunk)) {
      $bs = new BitSet();
      $bs->pushArray($currentChunk);
      $this->addEncodingBlock(
        $encodingSet,
        array(
          "type" => "bitmap",
          "data" => $bs
        )
      );
    }
  }

  protected function addEncodingBlock(array &$encodingSet, array $block)
  {
    $encodingSet[] = $block;
  }

  protected function getInt32Bytes($val)
  {
    $ret = array();

    $mask = 0xff;
    for ($i = 0; $i < 4; $i++) {
      $bits = (($val & ($mask << ((3 - $i) * 8))) >> ((3 - $i) * 8)) & $mask;
      $ret[] = chr($bits);
    }

    return $ret;
  }
}
