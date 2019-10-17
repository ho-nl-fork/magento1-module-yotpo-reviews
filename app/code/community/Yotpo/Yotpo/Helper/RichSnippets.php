`<?php

class Yotpo_Yotpo_Helper_RichSnippets extends Mage_Core_Helper_Abstract {

    private $_config;

    public function __construct() {
        $this->_config = Mage::getStoreConfig('yotpo');
    }

    public function getRichSnippet() {

        try {

            $productId = Mage::registry('product')->getId();
            $snippet = Mage::getModel('yotpo/richsnippet')->getSnippetByProductIdAndStoreId($productId, Mage::app()->getStore()->getId());

            if (($snippet == null) || (!$snippet->isValid())) {
                //no snippet for product or snippet isn't valid anymore. get valid snippet code from yotpo api

                $res = Mage::helper('yotpo/apiClient')->createApiGet("products/" . ($this->getAppKey()) . "/richsnippet/" . $productId, 2);

                if ($res["code"] != 200) {
                    //product not found or feature disabled.
                    return "";
                }

                $body = $res["body"];
                $averageScore = $body->response->rich_snippet->reviews_average;
                $reviewsCount = $body->response->rich_snippet->reviews_count;
                $ttl = $body->response->rich_snippet->ttl;

                if ($snippet == null) {
                    $snippet = Mage::getModel('yotpo/richsnippet');
                    $snippet->setProductId($productId);
                    $snippet->setStoreId(Mage::app()->getStore()->getid());
                }

                $snippet->setAverageScore($averageScore);
                $snippet->setReviewsCount($reviewsCount);
                $snippet->setExpirationTime(date('Y-m-d H:i:s', time() + $ttl));
                $snippet->save();

                return array("average_score" => $averageScore, "reviews_count" => $reviewsCount);
            }
            return array("average_score" => $snippet->getAverageScore(), "reviews_count" => $snippet->getReviewsCount());
        } catch (Exception $e) {
            Mage::log($e);
        }
        return array();
    }

    public function getRichSnippetAllProducts()
    {
        try {
            if (!Mage::app()->getCache()->load("yotpo_reviews")) {
                //no snippet or snippet isn't valid anymore. get valid snippet code from yotpo api
                $res = Mage::helper('yotpo/apiClient')->createApiGet("apps/" . ($this->getAppKey()) . "/bottom_lines?utoken=" . Mage::helper('yotpo/apiClient')->oauthAuthentication(Mage::app()->getStore()->getStoreId()));
                if ($res["code"] != 200) {
                    //product not found or feature disabled.
                    return "";
                }
                $products = $res["body"]->response->bottomlines;
                $averageScore = 0;
                $reviewsCount = 0;
                foreach ($products as $product) {
                    $averageScore += $product->product_score;
                    $reviewsCount += $product->total_reviews;
                }
                $averageScore = $averageScore / max(1, count($products));
                Mage::app()->getCache()->save(serialize(array("average_score" => $averageScore, "reviews_count" => $reviewsCount)), "yotpo_reviews", array("yotpo_reviews"), (60 * 60 * 24));
                return array("average_score" => $averageScore, "reviews_count" => $reviewsCount);
            }
            $snippet = unserialize(Mage::app()->getCache()->load("yotpo_reviews"));
            return array("average_score" => $snippet['average_score'], "reviews_count" => $snippet['reviews_count']);
        } catch (Exception $e) {
            Mage::log($e);
        }
        return array();
    }

    private function getAppKey() {
        return trim(Mage::getStoreConfig('yotpo/yotpo_general_group/yotpo_appkey', Mage::app()->getStore()));
    }

}
