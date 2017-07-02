<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/1/27
 * Time: 下午4:29
 */

namespace dianjoy;


use DateTime;

class BetterDate extends DateTime {
  const MONTHS = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
  const FORMAT = 'Y-m-d';
  const FORMAT_MONTH = 'Y-m';
  const FORMATS = ['Y', 'm', 'y', 'H', 'i', 's'];

  public function __construct($year = 'now', $month = 0, $date = 0) {
    if (!is_string($year)) {
      $year = date( self::FORMAT, mktime(0, 0, 0, $month, $date, $year));
    }
    parent::__construct($year);
  }

  public function get_last_of_date_month( BetterDate $end = null ) {
    $last = new DateTime($this->format(self::FORMAT));
    $last->modify('last day of this month');

    return $end->isBefore($last) ? $end->format(self::FORMAT) : $last->format(self::FORMAT);
  }

  public function isBefore(DateTime $target, $type = null) {
    if (!$type) {
      return $this->getTimestamp() <= $target->getTimestamp();
    }

    $type = $type == 'y' ? strtoupper($type) : $type;
    $format = implode('-', array_slice(self::FORMATS, 0, array_search($type, self::FORMATS)));
    $date = $this->format($format);
    $target = $target->format($format);
    return $date <= $target;
  }

  public function isSameMonth( DateTime $end ) {
    return $this->format(self::FORMAT_MONTH) == $end->format(self::FORMAT_MONTH);
  }

  public function __toString(  ) {
    return $this->format(self::FORMAT);
  }

  public static function firstDayOfMonth($date) {
    $date = is_string($date) ? new BetterDate($date) : $date;
    $date->modify('first day of this month');
    return $date->format(self::FORMAT);
  }

  public static function lastDayOfMonth($date) {
    $date = is_string($date) ? new BetterDate($date) : $date;
    $date->modify('last day of this month');
    return $date->format(self::FORMAT);
  }
}