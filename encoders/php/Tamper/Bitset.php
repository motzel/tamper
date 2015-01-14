<?php namespace Tamper;

/**
 * @author      Bogdan Modzelewski <bogdan.modzelewski@procad.pl>
 * @copyright   (c) PROCAD SA
 * @link        https://github.com/motzel/tamper
 * @license     Apache 2.0 license
 */

/**
 * Class BitSet
 *
 * BitSet implemented with integers (32-bit or 64-bit depending on system it's running on)
 *
 * @package Tamper
 */
class BitSet
{
  /** @var integer size in bits in this system  */
  protected $intSize;

  /** @var array bit data */
  protected $data = array();

  /** @var int current size of data */
  protected $size = 0;

  /**
   * @param int $size initial size of the BitSet
   */
  public function __construct($size = 0)
  {
    $this->setup();

    $this->clearAll();
    $this->setSize($size);
  }

  /**
   * Get size of the BitSet
   *
   * @return int size in bits
   */
  public function getSize()
  {
    return $this->size;
  }

  /**
   * Get BitSet as bytes (string)
   * If size is not divisible by 8 then is right padded with zeros
   *
   * @return string
   */
  public function getBytes()
  {
    $out = "";

    $fullBytes = (int)floor($this->size / 8);
    $remainder = $this->size % 8;

    for ($i = 0; $i < $fullBytes; $i++) {
      $intIdx = (int)($i * 8 / $this->intSize);
      $mask = 0xff << (((PHP_INT_SIZE-1) - ($i % PHP_INT_SIZE)) * 8);

      $out .= chr((($this->data[$intIdx] & $mask) >> (((PHP_INT_SIZE-1) - ($i % PHP_INT_SIZE)) * 8)) & 0xff);
    }

    if ($remainder > 0) {
      $lastByte = 0;
      for ($i = 0; $i < $remainder; $i++) {
        $lastByte = $lastByte | ($this->get($fullBytes * 8 + $i) << (7 - $i));
      }

      $out .= chr($lastByte);
    }

    return $out;
  }

  /**
   * Get value of the bit
   *
   * @param $bitNum bit index
   *
   * @return int 1 or 0
   *
   * @throws \OutOfRangeException
   */
  public function get($bitNum)
  {
    if ($bitNum > $this->size - 1) {
      throw new \OutOfRangeException("Bit index out of range");
    }

    list($byteIdx, $andMask) = $this->getIndexAndMask($bitNum);

    return isset($this->data[$byteIdx])
      ? ($this->data[$byteIdx] & $andMask) != 0 ? 1 : 0
      : 0;
  }

  /**
   * Set value of the bit
   *
   * @param $bitNum bit index
   * @param int $val bit value (any non empty values counts as 1)
   *
   * @throws \OutOfRangeException
   */
  public function set($bitNum, $val = 1)
  {
    if ($bitNum > $this->size - 1) {
      throw new \OutOfRangeException("Bit index out of range");
    }

    $this->setFast($bitNum, $val);
  }

  /**
   * Set value of the bit
   * Faster version, no checking is done. Be careful!
   *
   * @param $bitNum bit index
   * @param int $val bit value (any non empty values counts as 1)
   */
  public function setFast($bitNum, $val = 1)
  {
    $byteIdx = (int)($bitNum / $this->intSize);
    $bitIdx = $bitNum % $this->intSize;
    $mask = 1 << ($this->intSize - 1 - $bitIdx);

    if (!isset($this->data[$byteIdx])) {
      $this->data[$byteIdx] = 0;
    }

    if (!empty($val)) {
      $this->data[$byteIdx] |= $mask;
    } else {
      $this->data[$byteIdx] &= $mask ^ -1;
    }
  }

  /**
   * Clear value of the bit (set bit with 0)
   *
   * @param $bitNum bit index
   */
  public function clear($bitNum)
  {
    $this->set($bitNum, 0);
  }

  /**
   * Clear all bits
   */
  public function clearAll()
  {
    $this->data = $this->size > 0 ? array_fill(0, ceil($this->size / $this->intSize), 0) : array();
  }

  /**
   * Add new bit to BitSet
   * Size of BitSet is increased by 1 after operation
   *
   * @param $bit bit value (any non empty values counts as 1)
   */
  public function push($bit)
  {
    $this->size++;

    $bitNum = $this->size - 1;
    $byteIdx = (int)($bitNum / $this->intSize);
    $bitIdx = $bitNum % $this->intSize;
    $mask = 1 << ($this->intSize - 1 - $bitIdx);

    if (!isset($this->data[$byteIdx])) {
      $this->data[$byteIdx] = 0;
    }

    if (!empty($bit)) {
      $this->data[$byteIdx] |= $mask;
    } else {
      $this->data[$byteIdx] &= $mask ^ -1;
    }
  }

  /**
   * Add new bits to BitSet
   * Size of BitSet is increased by size of array after operation
   *
   * @param array $bits bits array (any non empty array item counts as 1)
   */
  public function pushArray(array $bits)
  {
    $cnt = count($bits);
    $this->size += $cnt;
    $i = 0;
    foreach ($bits as $b) {
      $this->setFast($this->size - $cnt + ($i++), $b);
    }
  }

  /**
   * Add new bits to BitSet
   * Size of BitSet is increased by $bits
   *
   * @param integer $val value to push
   * @param integer $bits number of bits to push
   */
  public function pushVal($val, $bits)
  {
    $mask = 1 << ($bits - 1);
    while($mask) {
      $this->push($val & $mask);
      $mask >>= 1;
    }
  }

  /**
   * Add byte to BitSet
   * Size of BitSet is increased by 8 after operation

   * @param $byte byte to push
   */
  public function pushByte($byte)
  {
    $mask = 0x80;
    for ($i = 0; $i < 8; $i++) {
      $this->push($byte & ($mask >> $i));
    }
  }

  /**
   * Add 32-bit integer to BitSet
   * Size of BitSet is increased by 32 after operation

   * @param $int32 32-bit integer to push
   */
  public function pushInt32($int32)
  {
    $mask = 0xff;
    for ($i = 0; $i < 4; $i++) {
      $this->pushByte(($int32 >> ((3 - $i) * 8)) & $mask);
    }
  }

  /**
   * Pop bit from the BitSet
   * Size of BitSet is decreased by 1 after operation
   *
   * @return int bit value
   */
  public function pop()
  {
    $bit = $this->get($this->size - 1);
    $this->size--;

    return $bit;
  }

  /**
   * Set new size of BitSet
   * New bits are initialized with zeroes if size is increased
   *
   * @param $size new size in bits
   */
  public function setSize($size)
  {
    if ($size > $this->size) {
      $actualInts = (int)ceil($this->size / $this->intSize);
      $newInts = (int)ceil($size / $this->intSize) - $actualInts;
      for ($i = 0; $i < $newInts; $i++) {
        $this->data[] = (int)0;
      }
    } else {
      $newInts = (int)ceil($size / $this->intSize);
      $this->data = array_slice($this->data, 0, $newInts);
    }

    $this->size = $size;
  }

  protected function setup()
  {
    $this->intSize = PHP_INT_SIZE * 8;
  }

  protected function getIndexAndMask($bitNum)
  {
    list($wholeIdx, $bitIdx) = $this->getIndex($bitNum);

    $mask = 1 << ($this->intSize - 1 - $bitIdx);

    return array($wholeIdx, $mask);
  }

  protected function getIndex($bitNum)
  {
    $wholeIdx = (int)($bitNum / $this->intSize);
    $bitIdx = $bitNum % $this->intSize;

    return array($wholeIdx, $bitIdx);
  }

  /**
   * Get BitSet as string
   * Each 32 bits are separated by new line character
   *
   * @return string
   */
  public function __toString()
  {
    $out = "";

    $fullInts = (int)floor($this->size / $this->intSize);
    for($i=0; $i<$fullInts; $i++) {
      $out .= sprintf("%032s", decbin(isset($this->data[$i]) ? $this->data[$i] : 0));
    }

    $remainder = $this->size % $this->intSize;
    for($j=0; $j<$remainder; $j++) {
      $mask = 1 << ($this->intSize - 1 - $j);
      $out .= $mask & $this->data[$i] ? "1" : "0";
    }

    return $out;
  }
}
