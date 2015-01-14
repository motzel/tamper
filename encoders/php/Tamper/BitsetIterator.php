<?php namespace Tamper;

/**
 * @author      Bogdan Modzelewski <bogdan.modzelewski@procad.pl>
 * @copyright   (c) PROCAD SA
 * @link        https://github.com/motzel/tamper
 * @license     Apache 2.0 license
 */

/**
 * Class BitSetIterator
 *
 * Example usage:
 *
 * $bs = new BitSet();
 * for($i=0; $i<10; $i++) $bs->push($i % 2);
 * foreach(new BitSetIterator($bs) as $bit) echo $bit;
 *
 * @package Tamper
 */
class BitSetIterator implements \Iterator
{
  protected $bitSet;
  protected $position;

  public function __construct(BitSet $bitSet)
  {
    $this->bitSet = $bitSet;
    $this->rewind();
  }

  public function rewind()
  {
    $this->position = 0;
  }

  public function current()
  {
    return $this->bitSet->get($this->position);
  }

  public function key()
  {
    return $this->position;
  }

  public function next()
  {
    $this->position++;
  }

  public function valid()
  {
    return $this->position < $this->bitSet->getSize();
  }

}
