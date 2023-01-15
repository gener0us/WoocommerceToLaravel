<?php

class IndexController
{
    /**
     * @var string
     */
    protected string $consumer_secret;
    /**
     * @var string
     */
    protected string $consumer_key;
    /**
     * @var string
     */
    protected string $consumer_site;

    public function __construct()
    {
        $this->consumer_key = \Config::get('consumer_key');
        $this->consumer_secret = \Config::get('consumer_secret');
        $this->consumer_site = \Config::get('consumer_site');
    }

    /**
     * @return Json
     */
    public function catsIndex(): Json
    {
        try {
            $cats = $this->getCats();
            $products = $this->getHeadersProduct();
            return json_decode(json: (string)['data' => ['cats' => $cats, 'products' => $products]]);
        } catch (Exception $exception) {
            return json_decode(json: (string)['error' => $exception->getMessage()]);
        }
    }

    public function productsIndex()
    {
        $page = isset(request()->page) ? request()->page : 1;
        $cat = request()->cat == 0 ? null : request()->cat;
        $products = $this->getProducts($cat, $page)->json();
        $headers = $this->getProducts($cat, $page)->headers();
        return json_decode(json: (string)['data' => ['cat' => $cat, 'products' => $products, 'headers' => $headers]]);
    }

    /**
     * @return mixed
     */
    public function SaveProducts(): mixed
    {
        foreach (request()->ids as $id) {
            $product = $this->getProduct($id);
                $checkedProduct = $this->checkValues($product);
                $this->storeProduct($checkedProduct);
                foreach ($product['categories'] as $key=>$cat) {
                    $this->storeCategory($cat);
                }
                foreach ($product['images'] as $key => $img_wp) {
                    $product_image_name = end($img_wp['src']);
                    file_put_contents($product_image_name, file_get_contents($img_wp['src']));
                }
                $succ .= sprintf("محصول %s اضافه شد.<br>", $product['name']);
        }
        return json_decode(json:['succ'=> $succ]);
    }


    /**
     * @return mixed
     */
    protected function getCats(): mixed
    {
        return Http::get($this->consumer_site . '/wp-json/wc/v3/products/categories', [
            'consumer_secret' => $this->consumer_secret,
            'consumer_key' => $this->consumer_key,
        ])->json();
    }

    /**
     * @return mixed
     */
    protected function getHeadersProduct(): mixed
    {
        return Http::get($this->consumer_site . '/wp-json/wc/v3/products', [
            'consumer_secret' => $this->consumer_secret,
            'consumer_key' => $this->consumer_key])->headers();
    }

    /**
     * @param $cat
     * @param $page
     * @return mixed
     */
    protected function getProducts($cat, $page): mixed
    {
        return Http::get($this->consumer_site . '/wp-json/wc/v3/products', [
            'consumer_secret' => $this->consumer_secret,
            'consumer_key' => $this->consumer_key,
            'category' => $cat,
            'per_page' => request()->per_page,
            'page' => $page,
            'status' => 'publish',
        ]);
    }

    /**
     * @param $id
     * @return mixed
     */
    protected function getProduct($id): mixed
    {
        return Http::get($this->consumer_site . '/wp-json/wc/v3/products/' . $id, [
            'consumer_secret' => $this->consumer_secret,
            'consumer_key' => $this->consumer_key,
        ])->json();
    }

    /**
     * @param $values
     * @return array
     */
    protected function checkValues($values): array
    {
        return [
            'sale_price' => $this->checkSalePrice($values['sale_price']),
            'regular_price' => $this->checkRegularPrice($values['regular_price']),
            'stock_quantity' => $this->checkStockQuantity($values['stock_quantity']),
            'stock_status' => $this->checkStockStatus($values['stock_status'])
        ];
    }

    /**
     * @param $value
     * @return int
     */
    protected function checkSalePrice($value): int
    {
        return match ($value) {
            !isset($value) || $value == '' => 0,
            default => $value,
        };
    }

    protected function checkStockQuantity($value): int
    {
        return match ($value) {
            !isset($value) || $value == '' => 0,
            default => $value,
        };
    }

    protected function checkRegularPrice($value): int
    {
        return match ($value) {
            !isset($value) || $value == '' => 0,
            default => $value,
        };
    }

    protected function checkStockStatus($value): int
    {
        return match ($value) {
            !isset($value) || $value == 'instock' => 0,
            default => $value,
        };
    }

    /**
     * @param $values
     * @return mixed
     */
    protected function storeProduct($values): mixed
    {
        return Product::create([
            'slug' => $values['slug'],
            'price' => $values['regular_price'],
            'selling_price' => $values['sale_price'],
            'qty' => $values['stock_quantity'],
            'in_stock' => $values['stock_status'],
        ]);
    }

    /**
     * @param $values
     * @return mixed
     */
    protected function storeCategory($values): mixed
    {
        return Category::create([
            'slug' => $values['slug'],
            'title' => $values['title'],
            'is_active' => 1
        ]);
    }
}
