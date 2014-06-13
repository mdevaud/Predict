<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Predict\Controller;
use Predict\Form\AddPriceForm;
use Predict\Form\ConfigureForm;
use Predict\Form\DeletePriceForm;
use Predict\Form\EditPriceForm;
use Predict\Predict;
use Predict\Form\FreeShipping;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\AreaQuery;
use Thelia\Model\ConfigQuery;

/**
 * Class ConfigurationController
 * @package Predict\Controller
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ConfigurationController extends BaseAdminController
{
    public function setFreeshipping()
    {
        if(null !== $response = $this->checkAuth(
                [AdminResources::MODULE],
                ['Predict'],
                AccessManager::UPDATE
        )) {
            return $response;
        }

        $form = new FreeShipping($this->getRequest());
        $response=null;

        try {
            $vform = $this->validateForm($form);
            $data = $vform->get('freeshipping')->getData();

            ConfigQuery::write("predict_freeshipping", $data);
            $response = Response::create('');
        } catch (\Exception $e) {
            $response = JsonResponse::create(array("error"=>$e->getMessage()), 500);
        }

        return $response;
    }

    public function exapaqConfigure()
    {
        if(null !== $response = $this->checkAuth(
                [AdminResources::MODULE],
                ['Predict'],
                AccessManager::UPDATE
            )) {
            return $response;
        }
        $error_msg  = false                                 ;
        $save_mode  = "stay"                                ;
        $form       = new ConfigureForm($this->getRequest());

        try {
            $vform = $this->validateForm($form)                                                             ;

            $save_mode = $this->getRequest()->request->get("save_mode")                                     ;

            ConfigQuery::write("store_exapaq_account", $vform->get("account_number")->getData())            ;
            ConfigQuery::write("store_cellphone", $vform->get("store_cellphone")->getData())                ;
            ConfigQuery::write("store_predict_option", $vform->get("predict_option")->getData() ? "1":"")   ;

        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $form->setErrorMessage($error_msg);

            $this->getParserContext()
                ->addForm($form)
                ->setGeneralError($error_msg)
            ;
        }

        if ($save_mode !== "stay") {
            $this->redirectToRoute(
                "admin.module",[],
                ['_controller' => 'Thelia\\Controller\\Admin\\ModuleController::indexAction']
            );
        }

        return $this->render(
            "module-configure",
            [
                "module_code"   => "Predict"    ,
                "tab"           => "configure"  ,
            ]
        );
    }

    public function addPrice()
    {
        if(null !== $response = $this->checkAuth(
                [AdminResources::MODULE],
                ['Predict'],
                AccessManager::CREATE
            )) {
            return $response;
        }

        $error_msg = false;

        $form = new AddPriceForm($this->getRequest());

        try {
            $vform = $this->validateForm($form, "post");
        } catch(FormValidationException $e) {
            $error_msg = $this->createStandardFormValidationErrorMessage($e->getMessage());
        } catch(\Exception $e) {
            $error_msg = $e->getMessage();
        }
    }

    public function editPrice()
    {
        if(null !== $response = $this->checkAuth(
                [AdminResources::MODULE],
                ['Predict'],
                AccessManager::UPDATE
            )) {
            return $response;
        }

        $error_msg = false;

        $form = new EditPriceForm($this->getRequest());

        try {
            $vform = $this->validateForm($form, "post");
        } catch(FormValidationException $e) {
            $error_msg = $this->createStandardFormValidationErrorMessage($e->getMessage());
        } catch(\Exception $e) {
            $error_msg = $e->getMessage();
        }

    }

    public function deletePrice()
    {
        if(null !== $response = $this->checkAuth(
                [AdminResources::MODULE],
                ['Predict'],
                AccessManager::DELETE
            )) {
            return $response;
        }

        $error_msg = false;

        $form = new DeletePriceForm($this->getRequest());

        try {
            $vform = $this->validateForm($form, "post");
        } catch(FormValidationException $e) {
            $error_msg = $this->createStandardFormValidationErrorMessage($e->getMessage());
        } catch(\Exception $e) {
            $error_msg = $e->getMessage();
        }

    }

    public function editPrices()
    {
        if(null !== $response = $this->checkAuth(
                [AdminResources::MODULE],
                ['Predict'],
                AccessManager::UPDATE
            )) {
            return $response;
        }

        // Get data & treat
        $post = $this->getRequest();
        $operation = $post->get('operation');
        $area = $post->get('area');
        $weight = $post->get('weight');
        $price = $post->get('price');
        if( preg_match("#^add|delete$#", $operation) &&
            preg_match("#^\d+$#", $area) &&
            preg_match("#^\d+\.?\d*$#", $weight)
        ) {
            // check if area exists in db
            $exists = AreaQuery::create()
                ->findPK($area);
            if ($exists !== null) {
                $json_path= __DIR__."/../".Predict::JSON_PRICE_RESOURCE;

                if (is_readable($json_path)) {
                    $json_data = json_decode(file_get_contents($json_path),true);
                } elseif (!file_exists($json_path)) {
                    $json_data = array();
                } else {
                    throw new \Exception("Can't read Predict".Predict::JSON_PRICE_RESOURCE.". Please change the rights on the file.");
                }
                if((float) $weight > 0 && $operation == "add"
                    && preg_match("#\d+\.?\d*#", $price)) {
                    $json_data[$area]['slices'][$weight] = round((float) $price, 2);
                } elseif ($operation == "delete") {
                    if(isset($json_data[$area]['slices'][$weight]))
                        unset($json_data[$area]['slices'][$weight]);
                } else {
                    throw new \Exception("Weight must be superior to 0");
                }
                ksort($json_data[$area]['slices']);
                if ((file_exists($json_path) ?is_writable($json_path):is_writable(__DIR__."/../"))) {
                    $file = fopen($json_path, 'w');
                    fwrite($file, json_encode($json_data));;
                    fclose($file);
                } else {
                    throw new \Exception("Can't write Predict".Predict::JSON_PRICE_RESOURCE.". Please change the rights on the file.");
                }
            } else {
                throw new \Exception("Area not found");
            }
        } else {
            throw new \ErrorException("Arguments are missing or invalid");
        }

        return $this->render(
            "module-configure",
            [
                "module_code"   => "Predict"    ,
                "tab"           => "prices"     ,
            ]
        );
    }}