<?php

namespace Zen\Snowflake\Resolvers;

use Illuminate\Contracts\Cache\Repository;
use Zen\Snowflake\Contracts\SequenceResolver;

class LaravelResolver implements SequenceResolver
{
  /**
   * The laravel cache instance.
   */
  protected Repository $cache;

  /**
   * Init resolve instance, must connectioned.
   */
  public function __construct(Repository $cache)
  {
    $this->cache = $cache;
  }

  /**
   * Increment the sequence.
   */
  public function sequence(int $currentTime): int
  {
    $key = $currentTime;

    if ($this->cache->add($key, 1, 1)) {
      return 0;
    }

    return $this->cache->increment($key, 1);
  }
}
