<?php


namespace CodeDelivery\Services;


use CodeDelivery\Models\Order;
use CodeDelivery\Repositories\AuxiliaryItemsRepository;
use CodeDelivery\Repositories\CupomRepository;
use CodeDelivery\Repositories\OrderRepository;
use CodeDelivery\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

class OrderService{
    /**
     * @var OrderRepository
     */
    private $orderRepository;
    /**
     * @var CupomRepository
     */
    private $cupomRepository;
    /**
     * @var ProductRepository
     */
    private $productRepository;



    public function __construct(
        OrderRepository $orderRepository,
        CupomRepository $cupomRepository,
        ProductRepository $productRepository

    )
    {

        $this->orderRepository = $orderRepository;
        $this->cupomRepository = $cupomRepository;
        $this->productRepository = $productRepository;

    }

    public function create(array $data){

        \DB::beginTransaction();

        try {
            $data['status'] = 0;

            if (isset($data['cupom_id'])){
                unset($data['cupom_id']);
            }
            if (isset($data['cupom_code'])){
                $cupom = $this->cupomRepository->findByField('code',$data['cupom_code'])->first();
                $data['cupom_id'] = $cupom->id;
                $cupom->used = 1;
                $cupom->save();
                unset($data['cupom_code']);
            }
            $items = $data['items'];
            $order = $this->orderRepository->create($data);

            $total = 0;
            foreach ($items as $item){
                $item['price'] = $this->productRepository->find($item['product_id'])->price;
                $order->items()->create($item);
                $total += $item['price'] * $item['qtd'];
            }

            $order->total = $total;

            if (isset($cupom)){
                $order->total = $total - $cupom->value;
            }
            $order->save();

            \DB::commit();

            return $order;
        } catch (\Exception $e){
             \DB::rollback();
            throw $e;
        }
    }

    public function updateStatus($id,$idDeliveryman,$status,$lat,$long,$service=null,$time=null){
        $order = $this->orderRepository->getByIDAndDeliveryman($id,$idDeliveryman);

        if($status == 1){
            $order->geo_client_no_location = $lat.','.$long;
        }elseif($status == 2){
            $order->geo = $lat.','.$long;
        }else{
            $order->geo_final = $lat.','.$long;
        }
        $order->status = $status;
        $order->service = $service;
        $order->time = $time;
        if((int)($order->status == 1 && !$order->hash)){
            $order->hash = md5((new \DateTime())->getTimestamp());
        }

        $order->save();
        return $order;

    }


}

