<?php

class StarpointController extends Starpoint
{
	public function procStarpointDoRateDocument()
	{
		$logged_info = Context::get('logged_info');
		$oStarPoint = getModel('starpoint');
		if (!$logged_info && !$oStarPoint->isGuestRateAllowed()) {
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$document_srl = Context::get('doc_srl');
		$star_srl = Context::get('star_srl');

		if(!$document_srl || !$star_srl){
			return new BaseObject(-1, '잘못된 요청입니다.');
		}

		if ($star_srl < 1 || $star_srl > 5) {
			return new BaseObject(-1, '별점은 1~5점 사이여야 합니다.');
		}
		
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if (!$oDocument->isExists()) {
			return new BaseObject(-1, '존재하지 않는 게시글입니다.');
		}

		$isRated = $oStarPoint->getIsRated($document_srl);

		if($isRated){
			return new BaseObject(-1, '이미 평가하셨습니다.');
		} else {
			if($logged_info){
				$result = $oStarPoint->insertStarRate($document_srl, $star_srl);
			} else {
				// 비로그인 평가: 회원번호는 0으로, 중복 방지를 위해 접속 IP를 함께 저장합니다.
				$result = $oStarPoint->insertStarRate($document_srl, $star_srl, 0, $oStarPoint->getClientIp());
			}
			if (!$result->toBool()) {
				return $result;
			}
		}

		$this->add('star_rate', $star_srl);
		
		$stats = $oStarPoint->getStarPointStatistics($document_srl);
		if ($stats) {
			$this->add('avg_rating', $stats->avg_rating);
			$this->add('total_ratings', $stats->total_ratings);
		}

		return new BaseObject();
	}

	public function procStarpointDeleteRating()
	{
		$logged_info = Context::get('logged_info');
		$oStarPoint = getModel('starpoint');
		if (!$logged_info && !$oStarPoint->isGuestRateAllowed()) {
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$document_srl = Context::get('document_srl');
		if (!$document_srl) {
			return new BaseObject(-1, '잘못된 요청입니다.');
		}

		$args = new stdClass();
		$args->document_srl = $document_srl;
		if ($logged_info) {
			$args->member_srl = $logged_info->member_srl;
		} else {
			// 비로그인 평가 취소는 회원번호 0 + 접속 IP가 일치하는 건만 대상으로 합니다.
			$args->member_srl = 0;
			$args->ipaddress = $oStarPoint->getClientIp();
		}

		$output = executeQuery('starpoint.deleteRating', $args);
		if (!$output->toBool()) {
			return $output;
		}

		$stats = $oStarPoint->getStarPointStatistics($document_srl);
		if ($stats) {
			$this->add('avg_rating', $stats->avg_rating);
			$this->add('total_ratings', $stats->total_ratings);
		}

		return new BaseObject();
	}
	
	/**
	 * @brief 문서 조회 후 트리거
	 */
	public function triggerGetDocumentEnd($obj)
	{
		$document_srl = $obj->document_srl;
		if(!$document_srl) {
			return $obj;
		}
		
		// 평점 정보 추가
		$oStarpointModel = getModel('starpoint');
		$stats = $oStarpointModel->getStarPointStatistics($document_srl);
		
		if($stats) {
			$obj->starpoint_avg = isset($stats->avg_rating) ? $stats->avg_rating : 0;
			$obj->starpoint_count = isset($stats->total_ratings) ? $stats->total_ratings : 0;
		}
		
		// 현재 로그인한 사용자의 평점 정보 추가
		$logged_info = Context::get('logged_info');
		if($logged_info && isset($logged_info->member_srl)) {
			$userRating = $oStarpointModel->getUserRating($document_srl, $logged_info->member_srl);
			if($userRating && isset($userRating->star_rate)) {
				$obj->user_starpoint = $userRating->star_rate;
			}
		}
		
		return $obj;
	}
}