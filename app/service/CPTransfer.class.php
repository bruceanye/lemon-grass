<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/8/17
 * Time: 下午6:36
 */

namespace diy\service;


use diy\model\DiyUserModel;
use Exception;

class CPTransfer extends Base {
  protected $ad_ids;
  protected $today;

  /**
   * @param array|string $ad_ids
   *
   * @return CPTransfer
   * @throws Exception
   */
  public static function createService( $ad_ids ) {
    switch ($_SESSION['type']) {
      case DiyUserModel::ANDROID_UNION:
        return new CPAndroidTransfer($ad_ids);
        break;

      case DiyUserModel::IOS_CP:
        return new CPiOSTransfer($ad_ids);
        break;

      default:
        throw new Exception('No cp type', 100000);
    }
  }

  /**
   * CPTransfer constructor.
   *
   * @param array|string $ad_ids
   */
  public function __construct( $ad_ids ) {
    $this->ad_ids = $ad_ids;
    $this->today = date( 'Y-m-d' );
  }

  public function fetch(  ) {

  }

  public function fetchDashboardData() {

  }

  public function getADStat( $start, $end ) {

  }

  public function merge( array $ads, array $fields ) {
    return $ads;
  }

  protected function fillEmptyData( $chart ) {
    $month_ago = date('Y-m-d', time() - 86400 * 30);
    for ($date = $month_ago; $date < $this->today; ) {
      if (!isset($chart[$date])) {
        $chart[$date] = 0;
      }
      $date = date('Y-m-d', strtotime($date) + 86400);
    }
    return $chart;
  }
}