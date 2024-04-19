<?php

namespace Zen\Snowflake;

use Zen\Snowflake\Contracts\SequenceResolver;
use Zen\Snowflake\Contracts\SnowflakeDriver;
use Zen\Snowflake\Resolvers\RandomResolver;

class Snowflake implements SnowflakeDriver
{
  const MAX_TIMESTAMP_LENGTH = 41;

  const MAX_DATACENTER_LENGTH = 5;

  const MAX_WORKID_LENGTH = 5;

  const MAX_SEQUENCE_LENGTH = 12;

  const MAX_FIRST_LENGTH = 1;

  /**
   * The data center id.
   */
  protected ?int $datacenter;

  /**
   * The worker id.
   */
  protected ?int $workerid;

  /**
   * The Sequence Resolver instance.
   *
   * @var \Zen\Snowflake\SequenceResolver|null
   */
  protected SequenceResolver $sequence;

  /**
   * The start timestamp.
   */
  protected int $startTime;

  /**
   * Default sequence resolver.
   *
   * @var \Zen\Snowflake\SequenceResolver|null
   */
  protected SequenceResolver $defaultSequenceResolver;

  /**
   * Build Snowflake Instance.
   */
  public function __construct(?int $datacenter = null, ?int $workerid = null)
  {
    $maxDataCenter = -1 ^ (-1 << self::MAX_DATACENTER_LENGTH);
    $maxWorkId = -1 ^ (-1 << self::MAX_WORKID_LENGTH);

    // If not set datacenter or workid, we will set a default value to use.
    $this->datacenter = $datacenter > $maxDataCenter || $datacenter < 0 ? mt_rand(0, 31) : $datacenter;
    $this->workerid = $workerid > $maxWorkId || $workerid < 0 ? mt_rand(0, 31) : $workerid;
  }

  /**
   * Get snowflake id.
   */
  public function id(): string
  {
    $currentTime = $this->getCurrentMicrotime();
    while (($sequence = $this->callResolver($currentTime)) > (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH))) {
      usleep(1);
      $currentTime = $this->getCurrentMicrotime();
    }

    $workerLeftMoveLength = self::MAX_SEQUENCE_LENGTH;
    $datacenterLeftMoveLength = self::MAX_WORKID_LENGTH + $workerLeftMoveLength;
    $timestampLeftMoveLength = self::MAX_DATACENTER_LENGTH + $datacenterLeftMoveLength;

    return (string) ((($currentTime - $this->getStartTimeStamp()) << $timestampLeftMoveLength)
      | ($this->datacenter << $datacenterLeftMoveLength)
      | ($this->workerid << $workerLeftMoveLength)
      | ($sequence));
  }

  /**
   * Parse snowflake id.
   *
   * @param  bool  $transform
   */
  public function parseId(string $id, $transform = false): array
  {
    $id = decbin($id);

    $data = [
      'timestamp' => substr($id, 0, -22),
      'sequence' => substr($id, -12),
      'workerid' => substr($id, -17, 5),
      'datacenter' => substr($id, -22, 5),
    ];

    return $transform ? array_map(function ($value) {
      return bindec($value);
    }, $data) : $data;
  }

  /**
   * Get current microtime timestamp.
   */
  public function getCurrentMicrotime(): int
  {
    return floor(microtime(true) * 1000) | 0;
  }

  /**
   * Set start time (milliseconds).
   */
  public function setStartTimeStamp(int $startTime): Snowflake
  {
    $missTime = $this->getCurrentMicrotime() - $startTime;

    if ($missTime < 0) {
      throw new \Exception('The start time cannot be greater than the current time');
    }

    $maxTimeDiff = -1 ^ (-1 << self::MAX_TIMESTAMP_LENGTH);

    if ($missTime > $maxTimeDiff) {
      throw new \Exception(sprintf('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << %d), You can reset the start time to fix this', self::MAX_TIMESTAMP_LENGTH));
    }

    $this->startTime = $startTime;

    return $this;
  }

  /**
   * Get start timestamp (millisecond), If not set default to 2020-06-06 06:06:06.
   */
  public function getStartTimeStamp(): int
  {
    if ($this->startTime > 0) {
      return $this->startTime;
    }

    // We set a default start time if not set.
    $defaultTime = '2020-06-06 06:06:06';

    return strtotime($defaultTime) * 1000;
  }

  /**
   * Set Sequence Resolver.
   *
   * @param  \Zen\Snowflake\SequenceResolver|callable  $sequence
   */
  public function setSequenceResolver($sequence): Snowflake
  {
    $this->sequence = $sequence;

    return $this;
  }

  /**
   * Get Sequence Resolver.
   *
   * @return \Zen\Snowflake\SequenceResolver|callable|null
   */
  public function getSequenceResolver()
  {
    return $this->sequence;
  }

  /**
   * Get Default Sequence Resolver.
   *
   * @return \Zen\Snowflake\SequenceResolver
   */
  public function getDefaultSequenceResolver(): SequenceResolver
  {
    return $this->defaultSequenceResolver
      ?: $this->defaultSequenceResolver = new RandomResolver();
  }

  /**
   * Call resolver.
   *
   * @param  callable|\Zen\Snowflake\SequenceResolver  $resolver
   * @param  int  $maxSequence
   */
  protected function callResolver($currentTime): int
  {
    $resolver = $this->getSequenceResolver();

    if (is_callable($resolver)) {
      return $resolver($currentTime);
    }

    return is_null($resolver) || ! ($resolver instanceof SequenceResolver)
      ? $this->getDefaultSequenceResolver()->sequence($currentTime)
      : $resolver->sequence($currentTime);
  }
}
