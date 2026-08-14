<?php

/**
 * 게시글 별점리뷰 관리자 View
 *
 * Copyright 80san
 */
class StarpointAdminView extends Starpoint
{
	/**
	 * @brief 초기화
	 */
	public function init()
	{
		// 템플릿 경로 설정
		$this->setTemplatePath($this->module_path.'tpl');
	}

	/**
	 * @brief 별점 모듈 설정 페이지
	 */
	public function dispStarpointAdminConfig()
	{
		// 현재 설정 불러오기
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('starpoint');
		
		if(!$config) {
			$config = new stdClass();
			$config->use_starpoint = 'N';
			$config->default_skin = 'simple';
			$config->starpoint_text = '글';
			$config->vote_members = 'Y';
			$config->vm_form = 'simple';
			$config->apply_type = 'all';
			$config->target_modules = array();
		}
		
		// 사용 가능한 스킨 목록 가져오기
		$skin_list = array();
		$skin_path = $this->module_path . 'skins/';
		if(is_dir($skin_path)) {
			$dirs = scandir($skin_path);
			foreach($dirs as $dir) {
				if($dir != '.' && $dir != '..' && is_dir($skin_path . $dir)) {
					$skin_list[] = $dir;
				}
			}
		}
		
		// 모듈 목록 가져오기 (게시판 선택용)
		$module_list = array();
		$args = new stdClass();
		$args->sort_index = "module_srl";
		$args->page = Context::get('page');
		$output = executeQueryArray('module.getModuleList', $args);
		if($output->toBool() && $output->data) {
			foreach($output->data as $module) {
				if($module->module == 'board') { // 게시판 모듈만
					$module_list[] = $module;
				}
			}
		}
		
		Context::set('config', $config);
		Context::set('skin_list', $skin_list);
		Context::set('module_list', $module_list);
		$this->setTemplateFile('config');
	}
}