<?php

/**
 * 게시글 별점리뷰 관리자 Controller
 *
 * Copyright 80san
 */
class StarpointAdminController extends Starpoint
{
	/**
	 * @brief 별점 모듈 설정 저장
	 */
	public function procStarpointAdminInsertConfig()
	{
		// 설정 값 받기
		$vars = Context::getRequestVars();
		
		$config = new stdClass();
		$config->use_starpoint = $vars->use_starpoint ? $vars->use_starpoint : 'N';
		$config->allow_guest_rate = $vars->allow_guest_rate ? $vars->allow_guest_rate : 'N';
		$config->default_skin = $vars->default_skin ? $vars->default_skin : 'simple';
		$config->starpoint_text = $vars->starpoint_text ? $vars->starpoint_text : '글';
		$config->vote_members = $vars->vote_members ? $vars->vote_members : 'Y';
		$config->vm_form = $vars->vm_form ? $vars->vm_form : 'simple';
		$config->apply_type = $vars->apply_type ? $vars->apply_type : 'all';
		$config->target_modules = $vars->target_modules ? $vars->target_modules : array();
		
		// 모듈 설정 저장
		$oModuleController = getController('module');
		$output = $oModuleController->insertModuleConfig('starpoint', $config);
		
		if(!$output->toBool()) {
			return $this->stop($output->getMessage());
		}
		
		// 캐시 삭제
		FileHandler::removeFilesInDir('./files/cache/starpoint');
		
		$this->setMessage('success_updated');
		
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispStarpointAdminConfig'));
	}
}