<?php
/**
 * 进口税金计算
 */

namespace App\Common\Lib;

class ChinaImportTaxCalc
{

    /**
     * @DOC
     * @Name   calc
     * @param float $taxRate 税率
     * @param float $dutiableValue 完税价格
     * @param float $price 价格
     * @param int $goodsNumber 商品数量
     * @param float $containNumber 内置数量
     * @return float
     */
    public function calc(float $taxRate, float $dutiableValue, float $price, int $goodsNumber, float $containNumber = 1)
    {

        if ($containNumber == 0) return 0;
        //根据数量计算单个价格
        $price = $price / $containNumber;
        //生成最终税金
        if (empty($taxRate)) {
            $taxFee = 0;
        }/* else if (empty($dutiableValue)) {
            $taxFee = $price * ($taxRate / 100) * $containNumber * $goodsNumber;
        } else if ($price >= 0.5 * $dutiableValue && $price < 2 * $dutiableValue) {
            $taxFee = $dutiableValue * ($taxRate / 100) * $containNumber * $goodsNumber;
        }*/
        else {
            $taxFee = $price * ($taxRate / 100) * $containNumber * $goodsNumber;
        }
        return $taxFee;
    }


}