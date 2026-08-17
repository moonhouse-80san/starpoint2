<?php

/**
 * 게시글 별점리뷰
 *
 * Copyright 80san
 *
 * Generated with https://www.poesis.dev/tools/modulegen
 */
class StarpointModel extends Starpoint
{

	public static function getIsRated($document_srl){
		//회원 정보와 게시글 번호를 기준으로 이미 기존에 평점을 남겼는지 체크 합니다.

		$logged_info = Context::get('logged_info');
		if($logged_info && !empty($logged_info->member_srl)){
			$args = new stdClass();
			$args->member_srl = $logged_info->member_srl;
			$args->document_srl = $document_srl;

			$output = executeQuery('starpoint.getRated', $args);

			return $output->data;
		}

		// 비로그인 사용자: 설정에서 허용된 경우에 한해 IP 주소 기준으로 중복 평가 여부를 체크합니다.
		if(!self::isGuestRateAllowed()){
			return false;
		}

		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->ipaddress = self::getClientIp();

		$output = executeQuery('starpoint.getRatedGuest', $args);

		return $output->data;
	}

	/**
	 * @brief 비로그인 평가 허용 여부 확인
	 */
	public static function isGuestRateAllowed(){
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('starpoint');
		return ($config && isset($config->allow_guest_rate) && $config->allow_guest_rate === 'Y');
	}

	/**
	 * @brief 접속자 IP 주소 가져오기 (게스트 평가 중복 방지용)
	 *
	 * Rhymix 부트스트랩에서 정의하는 RX_CLIENT_IP 상수를 사용합니다.
	 * (프록시 관련 헤더까지 고려해 실제 접속 IP를 판별하므로 $_SERVER['REMOTE_ADDR']를 직접 읽는 것보다 정확합니다)
	 */
	public static function getClientIp(){
		if(defined('RX_CLIENT_IP') && RX_CLIENT_IP){
			return substr(RX_CLIENT_IP, 0, 45);
		}
		return isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '';
	}

	public static function getDocumentRatedTotalPoint($document_srl){
		//게시글에 있는 전체 평점을 평균을 내어 가져옵니다.

		$args = new stdClass();
		$args->document_srl = $document_srl;

		$total_point = executeQuery('starpoint.getDocumentRatedTotalPoint', $args);
		return $total_point->data;
	}

	public static function insertStarRate($document_srl, $star_srl, $member_srl = null, $ipaddress = null){
		if($member_srl === null){
			$logged_info = Context::get('logged_info');
			if(!$logged_info){
				return new BaseObject(-1, '로그인이 필요합니다.');
			}
			$member_srl = $logged_info->member_srl;
		}

        $args = new stdClass();
        $args->document_srl = $document_srl;
        $args->member_srl = $member_srl;
        $args->star_rate = $star_srl;
        $args->regdate = date('YmdHis'); // 현재 시간을 YYYYMMDDhhmmss 형식으로 저장
        if($ipaddress !== null){
            $args->ipaddress = $ipaddress;
        }

		$result = executeQuery('starpoint.insertStarRate', $args);

		return $result;
	}

	/**
	 * @brief 사용자가 평가한 별점 정보 가져오기
	 * @param int $document_srl 문서 일련번호
	 * @param int $member_srl 회원 일련번호
	 * @return object 별점 정보
	 */
	public function getUserRating($document_srl, $member_srl = null) {
		if (!$member_srl) {
			$logged_info = Context::get('logged_info');
			if (!$logged_info) return null;
			$member_srl = $logged_info->member_srl;
		}

		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->member_srl = $member_srl;

		$output = executeQuery('starpoint.getRatingByMember', $args);
		if (!$output->toBool() || !$output->data) return null;
		
		return $output->data;
	}

	/**
	 * @brief 문서의 평점 통계 정보 가져오기
	 * @param int $document_srl 문서 일련번호
	 * @return object 평점 통계 정보
	 */
	public function getStarPointStatistics($document_srl) {
		$args = new stdClass();
		$args->document_srl = $document_srl;

		// 평균 평점과 평가 수 가져오기
		$output = executeQuery('starpoint.getRatingStatistics', $args);
		if (!$output->toBool()) return $output;

		return $output->data;
	}

	/**
	 * @brief 문서에 평점을 준 회원 목록 가져오기
	 * @param int $document_srl 문서 일련번호
	 * @return array 평점을 준 회원 목록
	 */
	public function getRatingList($document_srl) {
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->list_count = 100; // 최대 100명

		$output = executeQueryArray('starpoint.getRatingList', $args);
		
		if (!$output->toBool()) {
			return array();
		}

		return $output->data ? $output->data : array();
	}
}