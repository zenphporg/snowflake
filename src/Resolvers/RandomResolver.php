<?php

namespace Zen\Snowflake\Resolvers;

use Zen\Snowflake\Contracts\SequenceResolver;

class RandomResolver implements SequenceResolver
{
  /**
   * The last timestamp.
   */
  protected ?int $lastTimeStamp = -1;

  /**
   * The sequence.
   */
  protected int $sequence = 0;

  /**
   * Increment the sequence.
   */
  public function sequence(int $currentTime): int
  {
    if ($this->lastTimeStamp === $currentTime) {
      $this->sequence++;
      $this->lastTimeStamp = $currentTime;

      return $this->sequence;
    }

    $this->sequence = 0;
    $this->lastTimeStamp = $currentTime;

    return 0;
  }
}
