<?php
/**
 * @desc ebay
 * @author lihy
 *
 */
class EbayproductSellerRelationController extends UebController {
	
	/** @var object 模型实例 **/
	protected $_model = NULL;
	
	/**
	 * (non-PHPdoc)
	 * @see CController::init()
	 */
	public function init() {
		$this->_model = new EbayProductSellerRelation();
	}
	
	/**
	 * @desc 列表页
	 */
	public function actionList() {
		$request = http_build_query($_POST);

		//查询出搜索的总数
		$itemCount = $this->_model->search()->getTotalItemCount();

		$this->render("index", array("model"=>$this->_model, 'request'=>$request, 'itemCount'=>$itemCount));
	}
	
	public function actionImport() {
		if($_POST){
			set_time_limit(3600);
			ini_set('display_errors', true);
			ini_set('memory_limit', '256M');
			try{
				if(empty($_FILES['csvfilename']['tmp_name'])){
					throw new Exception("文件上传失败");
				}
				if($_FILES['csvfilename']['error'] != UPLOAD_ERR_OK){
					throw new Exception("文件上传失败, error:".$_FILES['csvfilename']['error']);
				}
				//限制下文件大小
				if($_FILES['csvfilename']['size'] > 2048000){
					echo $this->failureJson(array('message'=>"文件太大，在2M以下"));
					exit();
				}
				$file = $_FILES['csvfilename']['tmp_name'];
				
				
				$PHPExcel = new MyExcel();
				//excel处理
				Yii::import('application.vendors.MyExcel');
				$datas = $PHPExcel->get_excel_con($file);
				if(!empty($datas)){
					$ebayProductSellerRelationModel = new EbayProductSellerRelation();
					$sellerUserList = User::model()->getEbayUserList();
					$sellerUserList = array_flip($sellerUserList);

					//获取账号列表
					$accountList = UebModel::model("EbayAccount")->getIdNamePairs();

					//获取站点列表
					$siteArr = EbaySite::getSiteIdArr();

					//做日志
					foreach ($datas as $key=>$data){
						if($key == 1) continue;

						$dataA = str_replace('"', '', $data['A']);
						$dataA = trim($dataA);
						$dataB = str_replace('"', '', $data['B']);
						$dataB = trim($dataB);
						$dataC = str_replace('"', '', $data['C']);
						$dataC = trim($dataC);
						$dataE = str_replace('"', '', $data['E']);
						$dataE = trim($dataE);

						//@TODO 每个平台不一致
						$itemId 	= 	trim($dataA, "'");
						$sku 		= 	trim($dataB, "'");
						$onlineSku 	= 	trim($dataC, "'");
						$accountID 	=	$data['D'];
						$siteID		=	trim($dataE, "'");
						$sellerName = 	trim($data['F']);

						if(empty($itemId)){
							echo $this->failureJson(array('message'=>"在表格第{$key}行，ItemID为空"));
							exit();
						}

						if(empty($sku)){
							echo $this->failureJson(array('message'=>"在表格第{$key}行，sku为空"));
							exit();
						}

						if(empty($onlineSku)){
							echo $this->failureJson(array('message'=>"在表格第{$key}行，onlineSKU为空"));
							exit();
						}

						$existAccountID = isset($accountList[$accountID]) ? $accountList[$accountID] : '';
						if(empty($existAccountID)){
							echo $this->failureJson(array('message'=>"在表格第{$key}行，账号不存在或者该账号不属于这个平台"));
							exit();
						}

						if(!in_array($siteID, $siteArr)){
							echo $this->failureJson(array('message'=>"在表格第{$key}行，站点ID不正确"));
							exit();
						}

						$newSellerId = isset($sellerUserList[$sellerName]) ? $sellerUserList[$sellerName] : '';
						if(empty($newSellerId)){
							echo $this->failureJson(array('message'=>"在表格第{$key}行，销售人员账号不存在或者该账号不属于这个平台"));
							exit();
						}

					}


					foreach ($datas as $key=>$data){
						if($key == 1) continue;
						$dataA = str_replace('"', '', $data['A']);
						$dataA = trim($dataA);
						$dataB = str_replace('"', '', $data['B']);
						$dataB = trim($dataB);
						$dataC = str_replace('"', '', $data['C']);
						$dataC = trim($dataC);
						$dataE = str_replace('"', '', $data['E']);
						$dataE = trim($dataE);

						//@TODO 每个平台不一致
						$itemId 	= 	trim($dataA, "'");
						$sku 		= 	trim($dataB, "'");
						$onlineSku 	= 	trim($dataC, "'");
						$accountID 	=	$data['D'];
						$siteID		=	trim($dataE, "'");
						$sellerName = 	trim($data['F']);
						
						try{

							$newSellerId = isset($sellerUserList[$sellerName]) ? $sellerUserList[$sellerName] : '';

							//如果检测到位浮点类型,四舍五入，主要解决会出现小数点后面带99999999...的情况
							$sku = encryptSku::skuToFloat($sku);
							$onlineSku = encryptSku::skuToFloat($onlineSku);
							$insertData = array(
								'site_id'		=>	$siteID,
								'account_id'	=>	$accountID,
								'item_id'		=>	$itemId,
								'sku'			=>	$sku,
								'online_sku'	=>	$onlineSku,
							);
							
							//入库操作
							//检测是否存在
							if($existsId = $ebayProductSellerRelationModel->checkUniqueRow($itemId, $sku, $onlineSku, $accountID, $siteID)){
								//存在更新
								$res = $ebayProductSellerRelationModel->updateDataById($existsId, array('seller_id'=>$newSellerId));
								if(!$res){
									echo $this->failureJson(array('message'=>'Update Failure'));
									exit();
								}
							}else{//不存在插入
							
								$nowTime = date("Y-m-d H:i:s");
								$insertData['seller_id'] = $newSellerId;
								$insertData['create_time'] = $nowTime;
								$insertData['update_time'] = $nowTime;
								
								//@todo 准备改为批量添加的方式 lihy 0816
								$res = $ebayProductSellerRelationModel->saveData($insertData);
								if(!$res){
									echo $this->failureJson(array('message'=>'Insert Into Failure'));
									exit();
								}
							}
						}catch (Exception $e){
							$insertData['status'] = 1;
							$insertData['error_msg'] = $e->getMessage();
							$insertData['seller_id'] = 0;
							$ebayProductSellerRelationModel->writeProductSellerRelationLog($data);
						}
					}

				}

				echo $this->successJson(array('message'=>'success'));
				Yii::app()->end();
				exit;
			}catch (Exception $e){
				echo $this->failureJson(array('message'=>$e->getMessage()));
				Yii::app()->end();
			}
		}
		
		$this->render("upload");
		exit;
	}
	
	
	public function actionSaveimportdata(){
		error_reporting(E_ALL);
		set_time_limit(3600);
		ini_set('display_errors', true);
		try{
			$file = "./uploads/skuseller/ebay-0729.xlsx";
			$PHPExcel = new MyExcel();
			//excel处理
			Yii::import('application.vendors.MyExcel');
			$datas = $PHPExcel->get_excel_con($file);
			if(!empty($datas)){
				$ebayProductSellerRelationModel = new EbayProductSellerRelation();
				$sellerUserList = User::model()->getPairs();
				$sellerUserList = array_flip($sellerUserList);
				foreach ($datas as $key=>$data){
					if($key == 1) continue;
					//@TODO 每个平台不一致
					$itemId 	= 	$data['B'];
					$sku 		= 	$data['D'];
					$onlineSku 	= 	$data['E'];
					$accountID 	=	$data['G'];
					$siteID		=	$data['H'];
					$sellerName = 	trim($data['I']);
					$newSellerId = isset($sellerUserList[$sellerName]) ? $sellerUserList[$sellerName] : '';
					if(empty($newSellerId)) continue;
					//入库操作
					//检测是否存在
					if($existsId = $ebayProductSellerRelationModel->checkUniqueRow($itemId, $sku, $onlineSku, $accountID, $siteID)){
						//存在更新
						//$ebayProductSellerRelationModel->updateSellerIdByItemIdAndSku($newSellerId, $itemId, $sku, $onlineSku);
						$ebayProductSellerRelationModel->updateDataById($existsId, array('seller_id'=>$newSellerId));
					}else{//不存在插入
		
						$nowTime = date("Y-m-d H:i:s");
						$insertData = array(
								'site_id'		=>	$siteID,
								'account_id'	=>	$accountID,
								'item_id'		=>	$itemId,
								'sku'			=>	$sku,
								'online_sku'	=>	$onlineSku,
								'seller_id'		=>	$newSellerId,
								'create_time'	=>	$nowTime,
								'update_time'	=>	$nowTime
						);
						$ebayProductSellerRelationModel->saveData($insertData);
					}
				}
			}
			echo $this->successJson(array('message'=>'success'));
			Yii::app()->end();
			exit;
		}catch (Exception $e){
			echo $this->failureJson(array('message'=>$e->getMessage()));
			Yii::app()->end();
		}
		
	}
	
	public function actionSaveimportcsv(){
		error_reporting(E_ALL);
		set_time_limit(3600);
		ini_set('display_errors', true);
		ini_set('memory_limit', '2048M');
		try{
			$filename = Yii::app()->request->getParam("file");
			$ebayProductSellerRelationModel = new EbayProductSellerRelation();
			$sellerUserList = User::model()->getPairs();
			$sellerUserList = array_flip($sellerUserList);
			//$this->print_r($sellerUserList);
			if($filename){
				$file = "./uploads/skuseller/{$filename}.csv";
			}else{
				$file = "./uploads/skuseller/ebay-0729.csv";
			}
			echo $file;
			$fileHandle = fopen($file,'r');
			$key = 0;
			while ($data = fgetcsv($fileHandle)) {
					$key++;
					if($key == 1) continue;
					//@TODO 每个平台不一致
					$itemId 	= 	$data[1];
					$sku 		= 	$data[3];
					$onlineSku 	= 	$data[4];
					$accountID 	=	$data[6];
					$siteID		=	$data[7];
					$sellerName = 	trim($data[8]);
					$newSellerId = isset($sellerUserList[$sellerName]) ? $sellerUserList[$sellerName] : '';
					if(empty($newSellerId)) continue;
					//入库操作
					//检测是否存在
					if($existsId = $ebayProductSellerRelationModel->checkUniqueRow($itemId, $sku, $onlineSku, $accountID, $siteID)){
						//存在更新
						//$ebayProductSellerRelationModel->updateSellerIdByItemIdAndSku($newSellerId, $itemId, $sku, $onlineSku);
						$ebayProductSellerRelationModel->updateDataById($existsId, array('seller_id'=>$newSellerId));
					}else{//不存在插入
		
						$nowTime = date("Y-m-d H:i:s");
						$insertData = array(
								'site_id'		=>	$siteID,
								'account_id'	=>	$accountID,
								'item_id'		=>	$itemId,
								'sku'			=>	$sku,
								'online_sku'	=>	$onlineSku,
								'seller_id'		=>	$newSellerId,
								'create_time'	=>	$nowTime,
								'update_time'	=>	$nowTime
						);
						$ebayProductSellerRelationModel->saveData($insertData);
					}
				}
			echo $this->successJson(array('message'=>'success'));
			Yii::app()->end();
			exit;
		}catch (Exception $e){
			echo $this->failureJson(array('message'=>$e->getMessage()));
			Yii::app()->end();
		}
	}


	/**
	 * @desc 更改对应账号的销售人员
	 * @throws Exception
	 */
	public function actionBatchchangetoseller(){
		if($_POST){
			set_time_limit(3600);
			error_reporting(E_ALL);
			ini_set("display_errors", true);
			$logModel  = new EbayLog();
			$eventName = 'batchchangetoseller';
			try{
				$oldSiteId = Yii::app()->request->getParam('old_site_id');
				$oldAccountId = Yii::app()->request->getParam('old_account_id');
				$oldSellerId = Yii::app()->request->getParam('old_seller_id');
				$newSellerId = Yii::app()->request->getParam('EbayProductSellerRelation');
				if(!is_numeric($oldSiteId) || $oldSiteId < 0){
					throw new Exception("没有选择原有站点");
				}
				if(empty($oldAccountId)){
					throw new Exception("没有选择原有账号");
				}
				if(empty($oldSellerId)){
					throw  new Exception("没有选择原有的销售人员");
				}
				if(empty($newSellerId)){
					throw  new Exception("没有选择替换的销售人员");
				}

				//写log
                $logID = $logModel->prepareLog($oldAccountId, $eventName, $oldSiteId);
                if(!$logID){
                    exit('日志写入错误');
                }
                //检测是否可以允许
                if(!$logModel->checkRunning($oldAccountId, $eventName, $oldSiteId)){
                    $logModel->setFailure($logID, Yii::t('system', 'There Exists An Active Event'));
                    exit('There Exists An Active Event');
                }

                //设置运行
                $logModel->setRunning($logID);

				if(!$this->_model->batchChangeSellerToOtherSeller($oldSiteId, $oldAccountId, $oldSellerId, $newSellerId['seller_id'])){
					throw new Exception("更改失败！");
				}

				$jsonData = array(
						'message' => '更改成功',
						'forward' =>'/ebay/ebayproductsellerrelation/list',
						'navTabId'=> 'page' .EbayProductSellerRelation::getIndexNavTabId(),
						'callbackType'=>'closeCurrent'
				);

				$createUserId = isset(Yii::app()->user->id)?Yii::app()->user->id:0;
				$logModel->setSuccess($logID, "原销售人员ID:".$oldSellerId.'修改为:'.$newSellerId['seller_id'].'创建人为:'.$createUserId);
				echo $this->successJson($jsonData);

			}catch (Exception $e){
				if(isset($logID) && $logID){
                    $logModel->setFailure($logID, $e->getMessage());
                }
				echo $this->failureJson(array('message'=>$e->getMessage()));
			}
			Yii::app()->end();
		}
		//获取部门列表
		$userID = (int)Yii::app()->user->id;
		$department_id = User::model()->getDepIdById($userID);
		$ebayDepartArr = Department::model()->getDepartmentByPlatform(Platform::CODE_EBAY);
		if (in_array($department_id, $ebayDepartArr)) {
			$departList = Department::model()->findAll("id in ( " . $department_id . " )");
		}else{
			$departList = Department::model()->findAll("id in ( " . MHelper::simplode($ebayDepartArr) . " )");
		}
		$departmentList = array();
		foreach($departList as $value){
			$departmentList[$value['id']] = $value['department_name'];
		}

		//获取站点列表
		$siteList = UebModel::model('EbaySite')->getSiteList();
		//获取账号列表
		$accountList = UebModel::model("EbayAccount")->getIdNamePairs();
		//获取销售人员列表
		$sellerList = array('0'=>'-选择销售人员-');
		//$sellerList = User::model()->getEbayUserList();
		$allSellerList = User::model()->getEbayUserList(true);
		$this->render("batchchangetoseller", array('model'=>$this->_model, 'siteList'=>$siteList, 'accountList'=>$accountList, 'sellerList'=>$sellerList, 'allSellerList'=>$allSellerList,'departmentList'=>$departmentList));
		exit;
	}


	/**
	 * @desc 更新
	 * @throws Exception
	 */
	public function actionUpdate(){
		error_reporting(E_ALL);
		ini_set("display_errors", true);
		try{
			$id = Yii::app()->request->getParam("id");
			if(empty($id)) throw new Exception("参数不正确");
			$model = UebModel::model("EbayProductSellerRelation")->findByPk($id);
			if(empty($model)){
				throw new Exception("不存在该数据");
			}
			$model->account_name = UebModel::model("EbayAccount")->getAccountNameById($model->account_id);
			$model->site_name = EbaySite::model()->getSiteName($model->site_id);
			$this->render("update", array("model"=>$model, 'sellerList'=>User::model()->getEbayUserList()));
		}catch (Exception $e){
			echo $this->failureJson(array('message'=>$e->getMessage()));
		}
	}
	
	public function actionSavedata(){
		try{
			$id = Yii::app()->request->getParam("id");
			$ebayProductSellerRelation = Yii::app()->request->getParam("EbayProductSellerRelation");
			$sellerId = $ebayProductSellerRelation['seller_id'];
			$sku = $ebayProductSellerRelation['sku'];
			$onlineSku = $ebayProductSellerRelation['online_sku'];
			if(empty($id) || empty($sellerId) || empty($sku) || empty($onlineSku)){
				throw new Exception("参数不对");
			}
			$res = UebModel::model("EbayProductSellerRelation")->updateDataById($id, array('seller_id'=>$sellerId, 'sku'=>$sku, 'online_sku'=>$onlineSku));
			if(!$res){
				throw new Exception("操作失败");
			}

			$jsonData = array(
					'message' => '更改成功',
					'forward' =>'/ebay/ebayproductsellerrelation/list',
					'navTabId'=> 'page' .EbayProductSellerRelation::getIndexNavTabId(),
					'callbackType'=>'closeCurrent'
			);
			echo $this->successJson($jsonData);

			// echo $this->successJson(array('message'=>'更改成功'));
		}catch(Exception $e){
			echo $this->failureJson(array('message'=>$e->getMessage()));
		}
	}


	/**
	 * @desc 删除
	 * @throws Exception
	 */
	public function actionBatchdel(){
		try{
			$ids = Yii::app()->request->getParam("ids");
			$ebayProductSellerRelation = Yii::app()->request->getParam("EbayProductSellerRelation");
			
			if(empty($ids)){
				throw new Exception("参数不对");
			}
			$idArr = explode(",", $ids);
			$res = UebModel::model("EbayProductSellerRelation")->deleteById($idArr);
			if(!$res){
				throw new Exception("操作失败");
			}
			echo $this->successJson(array('message'=>'操作成功'));
		}catch(Exception $e){
			echo $this->failureJson(array('message'=>$e->getMessage()));
		}
	}


	/**
	 * @desc 获取未绑定的
	 */
	public function actionUnbindseller(){
        $request = http_build_query($_POST);

		$model = new EbayProductUnbindSellerRelation();

		//查询出搜索的总数
		$itemCount = $model->search()->getTotalItemCount();

		$this->render("unbindseller", array('model'=>$model, 'request'=>$request, 'itemCount'=>$itemCount));	
	}


	/**
	 * @desc 批量未绑定的到某一个人(账号操作)
	 */
	public function actionBatchchangeunbindtoseller(){
		if($_POST){
			error_reporting(E_ALL);
			ini_set("display_errors", true);
			try{
				$oldSiteId = Yii::app()->request->getParam('old_site_id');
				$oldAccountId = Yii::app()->request->getParam('old_account_id');
				$newSellerId = Yii::app()->request->getParam('EbayProductSellerRelation');
				if(!is_numeric($oldSiteId) || $oldSiteId < 0){
					throw new Exception("没有选择原有站点");
				}

				if(empty($oldAccountId)){
					throw new Exception("没有选择原有账号");
				}
	
				if(empty($newSellerId)){
					throw  new Exception("没有选择替换的销售人员");
				}
				if(!$this->_model->batchSetAccountListingToSeller($oldSiteId, $oldAccountId, $newSellerId['seller_id'])){
					throw new Exception("设置失败！");
				}

				$jsonData = array(
						'message' => '更改成功',
						'forward' =>'/ebay/ebayproductsellerrelation/unbindseller',
						'navTabId'=> 'page' . EbayProductSellerRelation::getUnbindsellerNavTabId(),
						'callbackType'=>'closeCurrent'
				);
				echo $this->successJson($jsonData);

				// echo $this->successJson(array('message'=>'设置成功'));
			}catch (Exception $e){
				echo $this->failureJson(array('message'=>$e->getMessage()));
			}
			Yii::app()->end();
		}
		//获取部门列表
		$userID = (int)Yii::app()->user->id;
		$department_id = User::model()->getDepIdById($userID);
		$ebayDepartArr = Department::model()->getDepartmentByPlatform(Platform::CODE_EBAY);
		if (in_array($department_id, $ebayDepartArr)) {
			$departList = Department::model()->findAll("id in ( " . $department_id . " )");
		}else{
			$departList = Department::model()->findAll("id in ( " . MHelper::simplode($ebayDepartArr) . " )");
		}
		$departmentList = array();
		foreach($departList as $value){
			$departmentList[$value['id']] = $value['department_name'];
		}

		//获取站点列表
		$siteList = UebModel::model('EbaySite')->getSiteList();
		//获取账号列表
		$accountList = UebModel::model("EbayAccount")->getIdNamePairs();
		//获取销售人员列表
		$sellerList = array('0'=>'-选择销售人员-');
		//$sellerList = User::model()->getEbayUserList();
		//$allSellerList = User::model()->getEbayUserList(true);
		$this->render("batchchangeunbindtoseller", array('model'=>$this->_model, 'siteList'=>$siteList, 'accountList'=>$accountList, 'sellerList'=>$sellerList, 'departmentList'=>$departmentList));
		exit;
	}

	
	/**
	 * @desc 批量改sku
	 */
	public function actionBatchchangeunbindskutoseller(){
		$ids = Yii::app()->request->getParam('ids');

		//获取销售人员列表
		$sellerList = User::model()->getEbayUserList();
		$this->render("batchchangeunbindskutoseller", array('model'=>$this->_model, "sellerList"=>$sellerList, "ids"=>rtrim($ids,',')));
		Yii::app()->end();
	}

	
	/**
	 * 保存批量设置sku给销售人员
	 * @throws Exception
	 */
	public function actionSavebatchsetunbindskutoseller(){
		error_reporting(E_ALL);
		ini_set("display_errors", true);
		try{
			
			$ids = Yii::app()->request->getParam('ids');
			$newSellerId = Yii::app()->request->getParam('EbayProductSellerRelation');
			if(empty($ids)){
				throw new Exception("没有选择SKU");
			}
			
			if(empty($newSellerId)){
				throw  new Exception("没有选择替换的销售人员");
			}

			$idArr = explode(",", $ids);
			if(!$this->_model->batchSetSkuListingToSeller($idArr, $newSellerId['seller_id'])){
				// throw new Exception("设置失败！");
				$jsonData = array(
					'message' => '账号已经被设置，请重新刷新页面！',
					'forward' =>'/ebay/ebayproductsellerrelation/unbindseller',
					'navTabId'=> 'page' . EbayProductSellerRelation::getUnbindsellerNavTabId(),
					'callbackType'=>'closeCurrent'
				);
				echo $this->successJson($jsonData);
				exit;
			}

			$jsonData = array(
				'message' => '更改成功',
				'forward' =>'/ebay/ebayproductsellerrelation/unbindseller',
				'navTabId'=> 'page' . EbayProductSellerRelation::getUnbindsellerNavTabId(),
				'callbackType'=>'closeCurrent'
			);
			echo $this->successJson($jsonData);
				
		}catch (Exception $e){
			echo $this->failureJson(array('message'=>$e->getMessage()));
		}
		Yii::app()->end();
	}


	/**
	 * @desc 导出产品与销售人员绑定的数据
	 */
	public function actionBindsellerexportxlsajax(){
		set_time_limit(3600);
		ini_set('display_errors', true);
		ini_set('memory_limit', '2048M');

		$conditions = 'id>:id';
    	$params[':id'] = 0;
    	$bool = 1;

    	$getParams = $_GET;
		if($getParams){
			if(isset($getParams['sku']) && $getParams['sku']){
				$conditions .= ' and sku LIKE "'.trim($getParams['sku']).'%"';
				// $params[':sku'] = $getParams['sku'];
			}

			if(isset($getParams['online_sku']) && $getParams['online_sku']){
				$conditions .= ' and online_sku LIKE "'.trim($getParams['online_sku']).'%"';
				// $params[':online_sku'] = $getParams['online_sku'];
			}

			if(isset($getParams['item_id']) && $getParams['item_id']){
				$conditions .= ' and item_id=:item_id';
				$params[':item_id'] = trim($getParams['item_id']);
			}

			if(isset($getParams['account_id']) && $getParams['account_id']){
				$conditions .= ' and account_id=:account_id';
				$params[':account_id'] = $getParams['account_id'];
			}

			if(isset($getParams['seller_id']) && $getParams['seller_id']){
				$conditions .= ' and seller_id=:seller_id';
				$params[':seller_id'] = $getParams['seller_id'];
			}

			if(isset($getParams['site_id']) && is_numeric($getParams['site_id']) && $getParams['site_id'] >= 0){
				$conditions .= ' and site_id=:site_id';
				$params[':site_id'] = $getParams['site_id'];
			}
		}

    	//从数据库中取出数据
		$datas = $this->_model->getBindSellerListByCondition($conditions,$params);
		if(!$datas){
			$bool = 0;
		}

		$this->render("unbindskutosellerajax", array('bool'=>$bool));
	}


	/**
	 * @desc 导出产品与销售人员绑定的数据
	 */
	public function actionBindsellerexportxls(){
		set_time_limit(3600);
		ini_set('display_errors', true);
		ini_set('memory_limit', '2048M');

		$conditions = 'id>:id';
    	$params[':id'] = 0;

    	$getParams = $_GET;
		if($getParams){
			if(isset($getParams['sku']) && $getParams['sku']){
				$conditions .= ' and sku LIKE "'.trim($getParams['sku']).'%"';
				// $params[':sku'] = $getParams['sku'];
			}

			if(isset($getParams['online_sku']) && $getParams['online_sku']){
				$conditions .= ' and online_sku LIKE "'.trim($getParams['online_sku']).'%"';
				// $params[':online_sku'] = $getParams['online_sku'];
			}

			if(isset($getParams['item_id']) && $getParams['item_id']){
				$conditions .= ' and item_id=:item_id';
				$params[':item_id'] = trim($getParams['item_id']);
			}

			if(isset($getParams['account_id']) && $getParams['account_id']){
				$conditions .= ' and account_id=:account_id';
				$params[':account_id'] = $getParams['account_id'];
			}

			if(isset($getParams['seller_id']) && $getParams['seller_id']){
				$conditions .= ' and seller_id=:seller_id';
				$params[':seller_id'] = $getParams['seller_id'];
			}

			if(isset($getParams['site_id']) && is_numeric($getParams['site_id']) && $getParams['site_id'] >= 0){
				$conditions .= ' and site_id=:site_id';
				$params[':site_id'] = $getParams['site_id'];
			}
		}

    	//从数据库中取出数据
		$datas = $this->_model->getBindSellerListByCondition($conditions,$params);
		if(!$datas){
			throw new Exception("无数据");
		}

		$str = "Item ID,SKU,在线SKU,账号ID,站点ID,销售人员,账号名称,站点名称\n";

		//取出所有销售人员
    	$allSellerList = User::model()->getAllUserName();

    	$siteList = EbaySite::model()->getSiteList();

    	$accountList = EbayAccount::model()->getIdNamePairs();

		foreach ($datas as $key => $value) {
			$sellName = isset($allSellerList[$value['seller_id']])?$allSellerList[$value['seller_id']]:'';
			$siteName = isset($siteList[$value['site_id']])?$siteList[$value['site_id']]:'';
			$accountName = isset($accountList[$value['account_id']])?$accountList[$value['account_id']]:'';

			$str .= "\t".trim($value['item_id']).",\t".$value['sku'].",\t".$value['online_sku'].",".$value['account_id'].",\t".$value['site_id'].",".$sellName.",".$accountName.",".$siteName."\n";
		}

		//导出文档名称
    	$exportName = 'ebay_绑定销售人员_sku_导出表'.date('Y-m-dHis').'.csv';

    	$this->export_csv($exportName,$str);
		exit;
	}


	/**
	 * @desc 导出产品与销售人员未绑定的数据
	 */
	public function actionUnbindsellerexportxlsajax(){
		set_time_limit(3600);
		ini_set('display_errors', true);
		ini_set('memory_limit', '2048M');

		$getParams = $_GET;   

		$conditions = 'ISNULL(s.seller_id) and p.item_status=:status';
		$letter = 'v';

		$params[':status'] = 1;
		$bool = 1;

		if(isset($getParams['sku']) && $getParams['sku']){
			$conditions .= ' and '.$letter.'.sku LIKE "'.trim($getParams['sku']).'%"';
			// $params[':sku'] = $getParams['sku'];
		}

		if(isset($getParams['online_sku']) && $getParams['online_sku']){
			$conditions .= ' and '.$letter.'.sku_online LIKE "'.trim($getParams['online_sku']).'%"';
			// $params[':online_sku'] = $getParams['online_sku'];
		}

		if(isset($getParams['item_id']) && $getParams['item_id']){
			$conditions .= ' and '.$letter.'.item_id=:item_id';
			$params[':item_id'] = trim($getParams['item_id']);
		}

		if(isset($getParams['account_id']) && $getParams['account_id']){
			$conditions .= ' and p.account_id=:account_id';
			$params[':account_id'] = $getParams['account_id'];
		}

		if(isset($getParams['site_id']) && is_numeric($getParams['site_id']) && $getParams['site_id'] >= 0){
			$conditions .= ' and p.site_id=:site_id';
			$params[':site_id'] = $getParams['site_id'];
		}

    	$datas = $this->_model->getUnBindSellerListByCondition($conditions,$params);
    	if(!$datas){
			$bool = 0;
		}

		$this->render("unbindskutosellerajax", array('bool'=>$bool));
	}


	/**
	 * @desc 导出产品与销售人员未绑定的数据
	 */
	public function actionUnbindsellerexportxls(){
		set_time_limit(3600);
		ini_set('display_errors', true);
		ini_set('memory_limit', '2048M');

		$getParams = $_GET;   

		$conditions = 'ISNULL(s.seller_id) and p.item_status=:status';
		$letter = 'v';

		$params[':status'] = 1;

		if(isset($getParams['sku']) && $getParams['sku']){
			$conditions .= ' and '.$letter.'.sku LIKE "'.trim($getParams['sku']).'%"';
			// $params[':sku'] = $getParams['sku'];
		}

		if(isset($getParams['online_sku']) && $getParams['online_sku']){
			$conditions .= ' and '.$letter.'.sku_online LIKE "'.trim($getParams['online_sku']).'%"';
			// $params[':online_sku'] = $getParams['online_sku'];
		}

		if(isset($getParams['item_id']) && $getParams['item_id']){
			$conditions .= ' and '.$letter.'.item_id=:item_id';
			$params[':item_id'] = trim($getParams['item_id']);
		}

		if(isset($getParams['account_id']) && $getParams['account_id']){
			$conditions .= ' and p.account_id=:account_id';
			$params[':account_id'] = $getParams['account_id'];
		}

		if(isset($getParams['site_id']) && is_numeric($getParams['site_id']) && $getParams['site_id'] >= 0){
			$conditions .= ' and p.site_id=:site_id';
			$params[':site_id'] = $getParams['site_id'];
		}

    	$datas = $this->_model->getUnBindSellerListByCondition($conditions,$params);
    	if(!$datas){
			throw new Exception("无数据");
		}

		$siteList = EbaySite::model()->getSiteList();

    	$accountList = EbayAccount::model()->getIdNamePairs();

		$str = "Item ID,SKU,在线SKU,账号ID,站点ID,销售人员,账号名称,站点名称\n";

		foreach ($datas as $key => $value) {
			$siteName = isset($siteList[$value['site_id']])?$siteList[$value['site_id']]:'';
			$accountName = isset($accountList[$value['account_id']])?$accountList[$value['account_id']]:'';

			$str .= "\t".trim($value['item_id']).",\t".$value['sku'].",\t".$value['sku_online'].",".$value['account_id'].",\t".$value['site_id'].",,".$accountName.",".$siteName."\n";
		}

		//导出文档名称
    	$exportName = 'ebay_未绑定销售人员_sku_导出表'.date('Y-m-dHis').'.csv';

    	$this->export_csv($exportName,$str);
		exit;
	}


	/**
	 * 定时绑定sku与销售人员(旧)
	 */
	public function actionSetunbindskutosellerrelationOld(){
		set_time_limit(3600);
		error_reporting(E_ALL);
		ini_set("display_errors", true);
		$limit 							= Yii::app()->request->getParam('limit', '');
		$ebayAccountModel 				= new EbayAccount();
		$UnbindSellerRelationModel 		= new EbayProductUnbindSellerRelation();
		$ebayProductAdd             	= new EbayProductAdd();
		$ebayProductSellerRelationModel = new EbayProductSellerRelation();
		$ebayLog 						= new EbayLog();

		//获取账号列表
		$accountList = UebModel::model("EbayAccount")->getIdNamePairs();

		//取出销售人员信息
		$sellerUserList = User::model()->getEbayUserList();

		//获取站点列表
		$siteArr = EbaySite::getSiteIdArr();

		$ebayAccountInfo = $ebayAccountModel->findAll('id > 0');
		foreach ($ebayAccountInfo as $key => $value) {
			$unBindSkuInfo = $UnbindSellerRelationModel->getUnbindSkuByAccountId($value->id,$limit);
			if(!$unBindSkuInfo){
				continue;
			}

			$eventName = "ebay_product_seller_relation";
			$logParams = array(
                'account_id'    => $value->id,
                'event'         => $eventName,
                'start_time'    => date('Y-m-d H:i:s'),
                'response_time' => date('Y-m-d H:i:s'),
                'create_user_id'=> Yii::app()->user->id ? Yii::app()->user->id : User::admin(),
                'status'        => EbayLog::STATUS_DEFAULT,
	        );
			$logID = $ebayLog->savePrepareLog($logParams);
			if(!$logID) exit("NO CREATE LOG ID");

			if(!$ebayLog->checkRunning($value->id, $eventName)){
				$ebayLog->setFailure($logID, "EXISTS EVENT");
				continue;
			}

			$ebayLog->setRunning($logID);

			//循环插入到ebay产品listing与销售人员关联表
			foreach ($unBindSkuInfo as $skuInfo) {

				//通过主sku和账号ID查询刊登记录表里的销售人员ID
				$fields = 'create_user_id';
				$conditions = array('account_id'=>$value->id, 'site_id'=>$skuInfo['site_id'], 'seller_sku'=>$skuInfo['seller_sku']);
				$productInfo = $ebayProductAdd->getEbayProductAddInfo($conditions,'',$fields);
				if(!$productInfo){
					continue;
				}
				
				$newSellerId = $productInfo['create_user_id'];

				$itemId 	= $skuInfo['item_id'];
				$sku 		= $skuInfo['sku'];
				$onlineSku 	= $skuInfo['online_sku'];
				$accountID 	= $value->id;
				$siteID		= $skuInfo['site_id'];
				if(!isset($sellerUserList[$newSellerId])){
					continue;
				}

				if(empty($itemId) || empty($sku) || empty($onlineSku) || !isset($accountList[$accountID]) || !in_array($siteID, $siteArr)){
					continue;
				}

				//检测不够四位，不够的话前缀补零
				$sku = encryptSku::skuToFloat($sku);
				$onlineSku = encryptSku::skuToFloat($onlineSku);
				$insertData = array(
					'site_id'		=>	$siteID,
					'account_id'	=>	$accountID,
					'item_id'		=>	$itemId,
					'sku'			=>	$sku,
					'online_sku'	=>	$onlineSku,
				);

				try{					
					
					//入库操作
					//检测是否存在
					if($existsId = $ebayProductSellerRelationModel->checkUniqueRow($itemId, $sku, $onlineSku, $accountID, $siteID)){
						//存在更新
						$res = $ebayProductSellerRelationModel->updateDataById($existsId, array('seller_id'=>$newSellerId));
						if(!$res){
							echo $this->failureJson(array('message'=>'Update Failure'));
							exit();
						}
					}else{//不存在插入
					
						$nowTime = date("Y-m-d H:i:s");
						$insertData['seller_id'] = $newSellerId;
						$insertData['create_time'] = $nowTime;
						$insertData['update_time'] = $nowTime;

						$res = $ebayProductSellerRelationModel->saveData($insertData);
						if(!$res){
							echo $this->failureJson(array('message'=>'Insert Into Failure'));
							exit();
						}
					}
				}catch (Exception $e){
					$insertData['status'] = 1;
					$insertData['error_msg'] = $e->getMessage();
					$insertData['seller_id'] = 0;
					$ebayProductSellerRelationModel->writeProductSellerRelationLog($insertData);
				}
			}

			$ebayLog->setSuccess($logID, "done");
		}
	}

	/**
	 * 定时绑定sku与站点负责人（新）
	 * 2016-11-22
	 * qzz
	 * @link /ebay/ebayproductsellerrelation/Setunbindskutosellerrelation/sku/xx/product_id/xx/debug/1 单个
	 * @link /ebay/ebayproductsellerrelation/Setunbindskutosellerrelation/account_id/xx/limit/30/debug/1指定帐号多条
	 */
	public function actionSetunbindskutosellerrelation()
	{
		set_time_limit(7200);
		error_reporting(E_ALL);
		ini_set("display_errors", true);
		$limit = Yii::app()->request->getParam('limit', '');
		$productId = Yii::app()->request->getParam('product_id', '');
		$skuId = Yii::app()->request->getParam('sku');
		$accountId = Yii::app()->request->getParam('account_id');
		$debug = Yii::app()->request->getParam('debug');

		$ebayAccountModel               = new EbayAccount();
		$UnbindSellerRelationModel      = new EbayProductUnbindSellerRelation();
		$ebayProductSellerRelationModel = new EbayProductSellerRelation();
		$SellerUserToAccountSiteModel   = new SellerUserToAccountSite();
		$ProductToSellerRelationModel   = new ProductToSellerRelation();
		$ebayLog                        = new EbayLog();
		$productToAccountModel          = new ProductToAccount();

		//获取账号列表
		$accountList = UebModel::model("EbayAccount")->getIdNamePairs();

		//获取站点列表
		$siteArr = EbaySite::getSiteIdArr();

		//获取站点名称
		$siteNameArr = EbaySite::getSiteList();

		//取出销售人员信息
		$sellerUserList = User::model()->getEbayUserList();

		if($accountId){//调试
			$ebayAccountInfo = $ebayAccountModel->findAll("id=:id and status=:status",array(":id"=>$accountId,":status"=>EbayAccount::STATUS_OPEN));
		}else{
			$ebayAccountInfo = $ebayAccountModel->findAll("status=:status",array(":status"=>EbayAccount::STATUS_OPEN));
		}

		foreach ($ebayAccountInfo as $key => $value) {
			$unBindSkuInfo = $UnbindSellerRelationModel->getUnbindSkuByAccountId($value->id,$limit,$productId,$skuId);
			if (!$unBindSkuInfo) {
				if($debug) {
					echo $value->id . "帐号没找到未绑定的sku<br>";
				}
				continue;
			}
			try{
				$eventName = "ebay_product_siter_relation";
				//$logID = $ebayLog->prepareLog($value->id,$eventName);

				$logParams = array(
					'account_id'    => $value->id,
					'event'         => $eventName,
					'start_time'    => date('Y-m-d H:i:s'),
					'response_time' => date('Y-m-d H:i:s'),
					'create_user_id'=> Yii::app()->user->id ? Yii::app()->user->id : User::admin(),
					'status'        => EbayLog::STATUS_DEFAULT,
				);
				$logID = $ebayLog->savePrepareLog($logParams);
				if(!$logID) exit("NO CREATE LOG ID");

				if(!$ebayLog->checkRunning($value->id, $eventName)){
					$ebayLog->setFailure($logID, "EXISTS EVENT");
					exit("EXISTS EVENT");
				}

				$ebayLog->setRunning($logID);

				foreach ($unBindSkuInfo as $skuInfo) {
					$skuInfo['site'] = isset($siteNameArr[$skuInfo['site_id']]) ? $siteNameArr[$skuInfo['site_id']] : '';
					//通过站点和账号查询站点负责人
					$siteInfo = $SellerUserToAccountSiteModel->getsiterByCondition(Platform::CODE_EBAY,$value->id,$skuInfo['site']);

					if (!$siteInfo) {
						if($debug){
							echo $skuInfo['item_id']."没找到站点负责人<br>";
						}
						continue;
					}

					$accountSku = $skuInfo['main_sku'];
					//如果是单品，查询main_sku是否是多属性
					if($skuInfo['is_multiple'] == 0){
						$mainSku = ProductSelectAttribute::model()->getMainSku(null, $skuInfo['main_sku']);
						if($mainSku && $accountSku != $mainSku){
							$accountSku = $mainSku;
						}
					}

					$tableName = 'ueb_product_to_account_seller_platform_eb_'.$value->id;
					$fields    = 'seller_user_id';
					$wheres    = 'sku = \''.$accountSku.'\' AND site = \''.$skuInfo['site_name'].'\'';
					$productInfo = $productToAccountModel->getOneByCondition($tableName,$fields,$wheres);

					//通过sku和站点负责人去匹配对应的销售人员
					// $productInfo = $ProductToSellerRelationModel->getSellerListByCondition($skuInfo['sku'], $siteInfo['seller_user_id']);

					if (!$productInfo) {
						if($debug){
							echo $skuInfo['item_id']."没找到对应的销售<br>";
						}
						continue;
					}

					$newSellerId = $productInfo['seller_user_id'];
					$itemId = $skuInfo['item_id'];
					$sku = $skuInfo['sku'];
					$onlineSku = $skuInfo['online_sku'];
					$accountID = $value->id;
					$siteID = $skuInfo['site_id'];
					if (!isset($sellerUserList[$newSellerId])) {
						continue;
					}

					if (empty($itemId) || empty($sku) || empty($onlineSku) || !isset($accountList[$accountID]) || !in_array($siteID, $siteArr)) {
						continue;
					}

					$sku = encryptSku::skuToFloat($sku);
					$onlineSku = encryptSku::skuToFloat($onlineSku);
					$insertData = array(
						'site_id' => $siteID,
						'account_id' => $accountID,
						'item_id' => $itemId,
						'sku' => $sku,
						'online_sku' => $onlineSku,
					);

					try {
						//入库操作
						//检测是否存在
						if ($existsId = $ebayProductSellerRelationModel->checkUniqueRow($itemId, $sku, $onlineSku, $accountID, $siteID)) {
							continue;
						} else {//不存在插入

							$nowTime = date("Y-m-d H:i:s");
							$insertData['seller_id'] = $newSellerId;
							$insertData['create_time'] = $nowTime;
							$insertData['update_time'] = $nowTime;

							$res = $ebayProductSellerRelationModel->saveData($insertData);
							if (!$res) {
								echo $this->failureJson(array('message' => 'Insert Into Failure'));
								throw new Exception(json_encode($insertData).' Insert Into Failure');
								exit();
							}
						}
						if($debug){//调试打印信息
							echo "<br/>=================startmsg===================<br/>";
							echo 'product_id:'.$itemId."<br>";
							echo 'sku:'.$sku."<br>";
							echo 'account_id:'.$value->id."<br>";
							echo 'site_id:'.$siteID."<br>";
							echo 'seller:'.$sellerUserList[$newSellerId]."<br>";
							print_r($res);
							echo "<br/>=================endmsg===================<br/>";
						}
					} catch (Exception $e) {
						if($debug){
							echo $e->getMessage();
						}
						$insertData['status'] = 1;
						$insertData['error_msg'] = $e->getMessage();
						$insertData['seller_id'] = 0;
						$ebayProductSellerRelationModel->writeProductSellerRelationLog($insertData);
						throw new Exception($e->getMessage());
					}
					unset($insertData);
				}
				$ebayLog->setSuccess($logID, "done");
			}catch(Exception $ex) {
				$ebayLog->setFailure($logID, $ex->getMessage());
			}

		}
	}

	//选择部门
	public function actionSelectDepart(){
		$departID = Yii::app()->request->getParam("depart_id");
		try{

			$departUser = array('0'=>'-选择销售人员-');
			if($departID!=''){
				$departUser = User::model()->getEmpByDept($departID);
			}

			echo $this->successJson(array('message'=>'success','departUser'=>$departUser));
		}catch (Exception $e){
			echo $this->failureJson(array('message'	=>	$e->getMessage()));
		}
	}
}