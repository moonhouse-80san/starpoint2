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
		if(!$logged_info || empty($logged_info->member_srl)){
			return false;
		}

		$args = new stdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->document_srl = $document_srl;

		$output = executeQuery('starpoint.getRated', $args);

		return $output->data;

	}

	public static function getDocumentRatedTotalPoint($document_srl){
		//게시글에 있는 전체 평점을 평균을 내어 가져옵니다.

		$args = new stdClass();
		$args->document_srl = $document_srl;

		$total_point = executeQuery('starpoint.getDocumentRatedTotalPoint', $args);
		return $total_point->data;
	}

	public static function insertStarRate($document_srl, $star_srl){
		$logged_info = Context::get('logged_info');
		if(!$logged_info){
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

        $args = new stdClass();
        $args->document_srl = $document_srl;
        $args->member_srl = $logged_info->member_srl;
        $args->star_rate = $star_srl;
        $args->regdate = date('YmdHis'); // 현재 시간을 YYYYMMDDhhmmss 형식으로 저장

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