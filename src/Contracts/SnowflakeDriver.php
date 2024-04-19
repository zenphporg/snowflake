<?php

namespace Zen\Snowflake\Contracts;

interface SnowflakeDriver
{
  /**
   * Get snowflake id.
   */
  public function id(): string;

  /**
   * Parse snowflake id.
   *
   * @param  bool  $transform
   */
  public function parseId(string $id, $transform = false): array;
}
