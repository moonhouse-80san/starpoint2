<?php
/**
 * @class  starpointView
 * @author 80san
 * @brief 별점(평점) 모듈의 View 클래스
 */
class starpointView extends starpoint
{
	/**
	 * @brief 초기화
	 */
	public function init()
	{
		// Set template path
		$this->setTemplatePath($this->module_path.'tpl');
	}

	/**
	 * @brief 평점 위젯 표시
	 */
	function dispStarpointRating()
	{
		// Get document_srl from request
		$document_srl = Context::get('document_srl');
		if(!$document_srl) {
			return new BaseObject(-1, 'msg_invalid_request');
		}

		// Get document info
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists()) {
			return new BaseObject(-1, 'msg_invalid_document');
		}

		// Get current logged in user info
		$logged_info = Context::get('logged_info');
		$is_logged = Context::get('is_logged');

		// Get rating information
		$output = $this->getStarpointInfo($document_srl);
		
		// Set variables for template
		Context::set('document_srl', $document_srl);
		
		// 통계 정보 설정
		$rating_avg = $output->get('rating_avg') ? $output->get('rating_avg') : 0;
		$rating_count = $output->get('rating_count') ? $output->get('rating_count') : 0;
		
		Context::set('rating_avg', $rating_avg);
		Context::set('rating_count', $rating_count);
		Context::set('is_logged', $is_logged);
		
		// 모듈 설정 추가
		$config = $this->getConfig();
		Context::set('config', $config);
		
		// Check if user already rated
		$already_rated = false;
		if($is_logged && $logged_info) {
			$args = new stdClass();
			$args->document_srl = $document_srl;
			$args->member_srl = $logged_info->member_srl;
			$output = executeQuery('starpoint.getRatingByMember', $args);
			$already_rated = ($output->data) ? true : false;
		}
		Context::set('already_rated', $already_rated);

		// Set template file - skins 폴더로 경로 변경
		$skin = isset($config->default_skin) ? $config->default_skin : 'simple';
		$this->setTemplatePath($this->module_path . 'skins/' . $skin);
		$this->setTemplateFile('rating');
		
		// Return success
		return new BaseObject();
	}
	
	/**
	 * @brief 문서의 평점 정보 가져오기
	 */
	function getStarpointInfo($document_srl)
	{
		$total_point = $this->getDocumentRatedTotalPointSafe($document_srl);
		$output = new BaseObject();
		$output->add('rating_avg', isset($total_point->avg) ? (float)$total_point->avg : 0);
		$output->add('rating_count', isset($total_point->count_members) ? (int)$total_point->count_members : 0);
		return $output;
	}

	private function getDocumentRatedTotalPointSafe($document_srl)
	{
		$oStarPointModel = getModel('starpoint');
		return $oStarPointModel->getDocumentRatedTotalPoint($document_srl);
	}

	/**
	 * @brief 모듈 설정 가져오기
	 */
	function getConfig()
	{
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('starpoint');
		
		if(!$config) {
			$config = new stdClass();
			$config->use_starpoint = 'N';
			$config->default_skin = 'simple';
			$config->starpoint_text = '글';
			$config->allow_guest_rate = 'N';
		}
		
		return $config;
	}
	
	/**
	 * @brief 최종 페이지 출력 후 평점 위젯 자동 삽입
	 *
	 * 게시판 스킨을 수정하지 않고 라이믹스의 display 트리거에서
	 * 현재 문서를 찾아 본문 컨테이너의 마지막에 평점 위젯을 삽입합니다.
	 */
	public function triggerDisplayDocumentContent(&$output)
	{
		// Starpoint의 AJAX 처리 응답(JSON)에 HTML/CSS를 끼워 넣으면 exec_json이
		// parse error를 발생시키므로, act 이름과 무관하게 HTML 응답이 아닌 요청
		// (JSON/XMLRPC 등, 예: board.procBoardInsertDocument 같은 다른 모듈의 AJAX 처리)에는
		// 절대 개입하지 않습니다.
		if (in_array(Context::getRequestMethod(), array('JSON', 'XMLRPC'), true))
		{
			return $output;
		}

		$act = (string)Context::get('act');
		$module = (string)Context::get('module');
		if (strpos($act, 'procStarpoint') === 0)
		{
			return $output;
		}

		// 글쓰기/수정 폼(dispBoardWrite 계열)이나 목록(dispBoardContentList) 등에는
		// 평점 위젯을 삽입하지 않고, 게시글 본문을 실제로 보여주는 페이지
		// (dispBoardContent, 모바일 dispBoardMobileContent)에서만 삽입합니다.
		$content_view_acts = array('dispBoardContent', 'dispBoardMobileContent');
		if (!in_array($act, $content_view_acts, true))
		{
			return $output;
		}

		if (!is_string($output) || $output === '')
		{
			return $output;
		}

		$config = $this->getConfig();
		if (!$config || (($config->use_starpoint ?? 'N') !== 'Y'))
		{
			return $output;
		}

		// display 트리거에서는 게시판 스킨의 oDocument가 Context에 없을 수 있습니다.
		// 요청의 document_srl을 기준으로 문서를 다시 조회합니다.
		$document_srl = (int)Context::get('document_srl');
		if (!$document_srl)
		{
			$document_srl = (int)Context::get('doc_srl');
		}
		if (!$document_srl)
		{
			return $output;
		}

		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if (!$oDocument || !$oDocument->isExists())
		{
			return $output;
		}

		// 게시판 문서인지 확인합니다. 다른 모듈의 문서에는 삽입하지 않습니다.
		$module_srl = (int)$oDocument->get('module_srl');
		$oModuleModel = getModel('module');
		$document_module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if (!$document_module_info || ($document_module_info->module ?? '') !== 'board')
		{
			return $output;
		}

		// 선택 게시판 설정 지원. 구버전의 selected 값도 호환합니다.
		$apply_type = $config->apply_type ?? 'all';
		$target_modules = $config->target_modules ?? array();
		if (in_array($apply_type, array('select', 'selected'), true))
		{
			if (!is_array($target_modules))
			{
				$target_modules = array();
			}
			$target_modules = array_map('intval', $target_modules);
			if (!in_array($module_srl, $target_modules, true))
			{
				return $output;
			}
		}

		// 이미 삽입된 경우 중복 삽입하지 않습니다.
		$marker = 'starpoint-rating-' . $document_srl;
		if (strpos($output, $marker) !== false)
		{
			return $output;
		}

		// 템플릿에서 사용하는 Context 값을 준비합니다.
		Context::set('document_srl', $document_srl);
		Context::set('oDocument', $oDocument);
		Context::set('module_info', $config);
		Context::set('mi', $config);
		Context::set('config', $config);
		Context::set('logged_info', Context::get('logged_info'));
		Context::set('is_logged', (bool)Context::get('is_logged'));
		if (!Context::get('is_logged'))
		{
			$logged_info = Context::get('logged_info');
			Context::set('is_logged', $logged_info && !empty($logged_info->member_srl));
		}

		$skin = isset($config->default_skin) && $config->default_skin ? $config->default_skin : 'simple';
		$allowed_skins = array('simple', 'default', 'black_simple', 'black_default');
		if (!in_array($skin, $allowed_skins, true))
		{
			$skin = 'simple';
		}

		$skin_path = $this->module_path . 'skins/' . $skin;
		$template = new Rhymix\Framework\Template($skin_path, 'rating.html');
		if (!$template->exists())
		{
			return $output;
		}

		try
		{
			// compile()은 라이믹스 템플릿을 실제 HTML로 렌더링합니다.
			$ratingContent = $template->compile();
		}
		catch (Throwable $e)
		{
			return $output;
		}

		if (!is_string($ratingContent) || trim($ratingContent) === '')
		{
			return $output;
		}

		$ratingContent = '<div id="' . $marker . '" class="starpoint-display-insert">' . $ratingContent . '</div>';

		// 평가 회원 목록을 평점 위젯 바로 아래에 출력합니다.
		// 템플릿 내부의 DB 조회에 의존하지 않고 PHP에서 직접 렌더링하여
		// display 트리거에서도 안정적으로 출력되도록 합니다.
		$vote_members = isset($config->vote_members) ? (string)$config->vote_members : 'Y';
		$is_admin = false;
		$logged_info = Context::get('logged_info');
		if ($logged_info)
		{
			$is_admin = (isset($logged_info->is_admin) && $logged_info->is_admin === 'Y');
		}
		$show_members = ($vote_members === 'Y') || ($vote_members === 'A' && $is_admin);
		if ($show_members)
		{
			$oStarPointModel = getModel('starpoint');
			$rating_list = $oStarPointModel->getRatingList($document_srl);
			if (!is_array($rating_list))
			{
				$rating_list = array();
			}

			if (count($rating_list) > 0)
			{
				$form = isset($config->vm_form) ? (string)$config->vm_form : 'simple';
				$member_html = '<div class="starpoint-vote-members">';
				$member_html .= '<div class="vm_area">';

				if ($form === 'default')
				{
					$member_html .= '<div class="vm_body">';
					$member_html .= '<div class="vm_top" onclick="jQuery(this).next(\'.vm_detail_list\').slideToggle();">';
					$member_html .= '<h4>평가 참여 회원 (' . count($rating_list) . '명)</h4>';
					$member_html .= '<span class="vm_folding">▼ 클릭하여 펼치기/접기</span></div>';
					$member_html .= '<div class="vm_detail_list" style="display:none;"><table><thead><tr class="vm_tr"><th class="vm_th">회원</th><th class="vm_th">평점</th><th class="vm_th">평가일시</th></tr></thead><tbody>';
				}
				elseif ($form === 'both')
				{
					$member_html .= '<div class="vm_box"><strong style="font-weight:bold;">평가 참여 :</strong>';
				}
				else
				{
					$member_html .= '<div class="vm_box"><strong style="font-weight:bold;">평가 참여 :</strong>';
				}

				$oMemberModel = getModel('member');
				foreach ($rating_list as $rating)
				{
					$member_srl = isset($rating->member_srl) ? (int)$rating->member_srl : 0;
					$nick_name = isset($rating->nick_name) ? trim((string)$rating->nick_name) : '';
					if ($member_srl === 0) {
						$display_name = 'Guest';
					} else {
						$display_name = $nick_name !== '' ? $nick_name : '탈퇴회원';
					}
					$display_name = htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8');
					$star_rate = isset($rating->star_rate) ? (int)$rating->star_rate : 0;
					$regdate = isset($rating->regdate) ? $rating->regdate : '';

					if ($form === 'default')
					{
						$stars = str_repeat('★', max(0, min(5, $star_rate))) . str_repeat('☆', max(0, 5 - min(5, $star_rate)));
						$member_html .= '<tr class="vm_tr1"><td class="vm_th">' . $display_name . '</td><td class="vm_th"><span class="vm_star">' . $stars . '</span> <span class="vm_star_rate">' . $star_rate . '점</span></td><td class="vm_th" style="font-size:12px;">' . ($regdate ? zdate($regdate, 'Y-m-d H:i') : '') . '</td></tr>';
						continue;
					}

					$profile_url = RX_BASEURL . 'modules/starpoint/skins/ico_default.jpg';
					if ($member_srl > 0 && $oMemberModel)
					{
						try
						{
							$profile_image = $oMemberModel->getProfileImage($member_srl);
							if ($profile_image && !empty($profile_image->src)) $profile_url = $profile_image->src;
						}
						catch (Throwable $e) {}
					}
					$profile_url = htmlspecialchars($profile_url, ENT_QUOTES, 'UTF-8');
					$member_html .= '<span title="' . $display_name . '" class="pr_tooltip" pr_color="dark" pr_position="top"><img src="' . $profile_url . '" alt="' . $display_name . '">';
					if ($is_admin) $member_html .= '<span class="vm_rate">' . $star_rate . '점</span>';
					$member_html .= '</span>';
				}

				if ($form === 'default')
				{
					$member_html .= '</tbody></table></div></div>';
				}
				else
				{
					$member_html .= '</div>';
				}

				if ($form === 'both' && $is_admin)
				{
					$member_html .= '<div class="vm_admin" style="cursor:pointer;" role="button" tabindex="0" onclick="jQuery(this).next(\'.vm_admin_detail\').find(\'.vm_detail_list\').stop(true,true).slideToggle();"><strong>👤 관리자 상세보기 <span class="vm_admin_arrow">▼</span></strong></div>';
					$member_html .= '<div class="vm_admin_detail"><div class="vm_detail_list" style="display:none;"><table><thead><tr class="vm_tr"><th class="vm_th">회원</th><th class="vm_th">평점</th><th class="vm_th">평가일시</th></tr></thead><tbody>';
					foreach ($rating_list as $rating)
					{
						$row_member_srl = isset($rating->member_srl) ? (int)$rating->member_srl : 0;
						if ($row_member_srl === 0) {
							$name = 'Guest';
						} else {
							$name = isset($rating->nick_name) && $rating->nick_name !== '' ? htmlspecialchars($rating->nick_name, ENT_QUOTES, 'UTF-8') : '탈퇴회원';
						}
						$rate = (int)$rating->star_rate;
						$stars = str_repeat('★', max(0, min(5, $rate))) . str_repeat('☆', max(0, 5 - min(5, $rate)));
						$member_html .= '<tr class="vm_tr1"><td class="vm_th">' . $name . '</td><td class="vm_th"><span class="vm_star">' . $stars . '</span> ' . $rate . '점</td><td class="vm_th">' . (isset($rating->regdate) ? zdate($rating->regdate, 'Y-m-d H:i') : '') . '</td></tr>';
					}
					$member_html .= '</tbody></table></div></div>';
				}

				$member_html .= '</div></div>';
				$ratingContent .= $member_html;
			}
		}

		// display 트리거는 head가 이미 만들어진 뒤 호출되므로 CSS/JS를 직접 삽입합니다.
		$asset_base = RX_BASEURL . 'modules/starpoint/skins/';
		$asset_html = '<link rel="stylesheet" type="text/css" href="' . $asset_base . $skin . '/rating.css" />' .
			'<script type="text/javascript" src="' . $asset_base . 'rating.js"></script>';
		if ($show_members)
		{
			$asset_html .= '<link rel="stylesheet" type="text/css" href="' . $asset_base . 'vote_members.css" />';
		}

		if (stripos($output, 'modules/starpoint/skins/' . $skin . '/rating.css') === false)
		{
			$head_pos = stripos($output, '</head>');
			if ($head_pos !== false)
			{
				$output = substr($output, 0, $head_pos) . $asset_html . substr($output, $head_pos);
			}
			else
			{
				$body_pos = stripos($output, '<body');
				$output = ($body_pos !== false) ? substr($output, 0, $body_pos) . $asset_html . substr($output, $body_pos) : $asset_html . $output;
			}
		}

		// 가장 정확한 위치: 본문 컨테이너(.rd_body 등)의 닫는 div 바로 앞.
		$selectors = array(
			'/<div\b[^>]*class\s*=\s*["\'][^"\']*\brd_body\b[^"\']*["\'][^>]*>/i',
			'/<div\b[^>]*class\s*=\s*["\'][^"\']*\bxe_content\b[^"\']*["\'][^>]*>/i',
			'/<div\b[^>]*class\s*=\s*["\'][^"\']*\b(?:document-content|document_content|read_body|board_read)\b[^"\']*["\'][^>]*>/i',
			'/<div\b[^>]*id\s*=\s*["\'](?:document-content|document_content|xe_content|rd_body)["\'][^>]*>/i',
		);

		foreach ($selectors as $selector)
		{
			if (!preg_match($selector, $output, $match, PREG_OFFSET_CAPTURE))
			{
				continue;
			}

			$open_start = $match[0][1];
			$open_end = strpos($output, '>', $open_start);
			if ($open_end === false)
			{
				continue;
			}

			$scan = $open_end + 1;
			$depth = 1;
			$length = strlen($output);

			while ($scan < $length && $depth > 0)
			{
				$next_open = stripos($output, '<div', $scan);
				$next_close = stripos($output, '</div', $scan);

				if ($next_close === false)
				{
					break;
				}

				if ($next_open !== false && $next_open < $next_close)
				{
					$next_gt = strpos($output, '>', $next_open);
					if ($next_gt === false)
					{
						break;
					}
					$inner_tag = substr($output, $next_open, $next_gt - $next_open + 1);
					if (!preg_match('/\/\s*>$/', $inner_tag))
					{
						$depth++;
					}
					$scan = $next_gt + 1;
				}
				else
				{
					$next_gt = strpos($output, '>', $next_close);
					if ($next_gt === false)
					{
						break;
					}
					$depth--;
					if ($depth === 0)
					{
						$output = substr($output, 0, $next_close) . $ratingContent . substr($output, $next_close);
						return $output;
					}
					$scan = $next_gt + 1;
				}
			}
		}

		// 본문 클래스명이 다른 스킨은 본문 다음에 자주 등장하는 관련글/태그 영역 앞에 넣습니다.
		$fallback_markers = array(
			'/<div\b[^>]*(?:class|id)\s*=\s*["\'][^"\']*(?:related|comment|tag|reaction|xe_content)[^"\']*["\'][^>]*>/i',
			'/<div\b[^>]*class\s*=\s*["\'][^"\']*\btag\b[^"\']*["\'][^>]*>/i',
		);
		foreach ($fallback_markers as $fallback)
		{
			if (preg_match($fallback, $output, $match, PREG_OFFSET_CAPTURE))
			{
				$pos = $match[0][1];
				$output = substr($output, 0, $pos) . $ratingContent . substr($output, $pos);
				return $output;
			}
		}

		// 마지막 fallback: </article>, </main>, </body> 순으로 삽입합니다.
		foreach (array('</article>', '</main>', '</body>') as $closing_tag)
		{
			$pos = stripos($output, $closing_tag);
			if ($pos !== false)
			{
				$output = substr($output, 0, $pos) . $ratingContent . substr($output, $pos);
				return $output;
			}
		}

		$output .= $ratingContent;
		return $output;
	}

}