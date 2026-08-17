<?php

class Starpoint extends ModuleObject
{
	const STARPOINT_VERSION = '2.2';
	public function createObject($status = 0, $message = 'success')
	{
		$args = func_get_args();
		if (count($args) > 2)
		{
			global $lang;
			$message = vsprintf($lang->$message, array_slice($args, 2));
		}
		return class_exists('BaseObject') ? new BaseObject($status, $message) : new Object($status, $message);
	}

	public function moduleInstall()
	{
		$oModuleController = getController('module');
		
		// 문서 조회 후 트리거
		$oModuleController->insertTrigger('document.getDocumentEnd', 'starpoint', 'controller', 'triggerGetDocumentEnd', 'after');
		
		// 게시판 문서 출력 후 트리거: 게시판 스킨을 수정하지 않고 전체 HTML에서 본문 바로 아래에 삽입
		$oModuleController->insertTrigger('display', 'starpoint', 'view', 'triggerDisplayDocumentContent', 'after');
		
		return new BaseObject();
	}


	public function moduleUninstall()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		$triggers = array(
			array('document.getDocumentEnd', 'controller', 'triggerGetDocumentEnd', 'after'),
			array('display', 'view', 'triggerDisplayDocumentContent', 'after'),
			array('display.documentContent', 'view', 'triggerDisplayDocumentContent', 'after'),
		);

		foreach($triggers as $trigger)
		{
			if($oModuleModel->getTrigger($trigger[0], 'starpoint', $trigger[1], $trigger[2], $trigger[3]))
			{
				$oModuleController->deleteTrigger($trigger[0], 'starpoint', $trigger[1], $trigger[2], $trigger[3]);
			}
		}

		return new BaseObject();
	}

	public function checkUpdate()
	{
		$oModuleModel = getModel('module');
		
		if(!$oModuleModel->getTrigger('document.getDocumentEnd', 'starpoint', 'controller', 'triggerGetDocumentEnd', 'after'))
			return true;
			
		if(!$oModuleModel->getTrigger('display', 'starpoint', 'view', 'triggerDisplayDocumentContent', 'after'))
			return true;


		// 구버전의 잘못된 display.documentContent 트리거가 남아 있으면 업데이트가 필요합니다.
		if($oModuleModel->getTrigger('display.documentContent', 'starpoint', 'view', 'triggerDisplayDocumentContent', 'after'))
			return true;

		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('document_star', 'regdate')) return true;

		// V2.5: 비로그인(게스트) 평가 중복 방지를 위한 ipaddress 컬럼
		if(!$oDB->isColumnExists('document_star', 'ipaddress')) return true;

		// V2.4: 평가 회원 목록을 display 트리거에서 직접 렌더링
		$config = $oModuleModel->getModuleConfig('starpoint');
		if(!$config || !isset($config->version) || $config->version !== '2.5') return true;

		return false;
	}

public function moduleUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		
		if(!$oModuleModel->getTrigger('document.getDocumentEnd', 'starpoint', 'controller', 'triggerGetDocumentEnd', 'after'))
			$oModuleController->insertTrigger('document.getDocumentEnd', 'starpoint', 'controller', 'triggerGetDocumentEnd', 'after');
		
		// 구버전에서 잘못 등록된 display.documentContent 트리거 제거
		if($oModuleModel->getTrigger('display.documentContent', 'starpoint', 'view', 'triggerDisplayDocumentContent', 'after'))
			$oModuleController->deleteTrigger('display.documentContent', 'starpoint', 'view', 'triggerDisplayDocumentContent', 'after');

		if(!$oModuleModel->getTrigger('display', 'starpoint', 'view', 'triggerDisplayDocumentContent', 'after'))
			$oModuleController->insertTrigger('display', 'starpoint', 'view', 'triggerDisplayDocumentContent', 'after');

		$oDB = &DB::getInstance();
		if(!$oDB->isColumnExists('document_star', 'regdate')) 
		{
			$oDB->addColumn('document_star', 'regdate', 'date', 'idx_regdate');
		}

		if(!$oDB->isColumnExists('document_star', 'ipaddress'))
		{
			// addColumn() 시그니처: (table, column, type, size, default, notnull, after_column)
			// 인덱스명은 addColumn()의 인자가 아니라 addIndex()로 별도 생성해야 합니다.
			$oDB->addColumn('document_star', 'ipaddress', 'varchar', 45, '');
			if(!$oDB->isIndexExists('document_star', 'idx_ipaddress'))
			{
				$oDB->addIndex('document_star', 'idx_ipaddress', 'ipaddress');
			}
		}

		$config = $oModuleModel->getModuleConfig('starpoint');
		if(!$config) $config = new stdClass();
		if(!isset($config->allow_guest_rate)) $config->allow_guest_rate = 'N';
		$config->version = '2.5';
		$oModuleController->insertModuleConfig('starpoint', $config);

		return new BaseObject();
	}

	public function recompileCache()
	{
		FileHandler::removeFilesInDir('./files/cache/starpoint');
	}
}