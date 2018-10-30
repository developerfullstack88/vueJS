<?php

namespace App\Http\Controllers;

use Input;
use Illuminate\Http\Request;
use Auth;
use App\Item;
use App\Package;
use App\ShipmentOrder;
use App\AuctionItem;
use App\ItemImage;
use App\AppSetting;
use App\ItemLog;
use App\Customer;
use App\Customers;
use App\CustomerAddress;
use App\Models\Location;
use App\Models\Group;
use App\Models\User;
use Validator;
use Excel;
use DB;
use App\UserEmailTemplate;
use App\EmailTemplate;
use Mail;
use Response;
use App\ImportFormat;
use App\ExportFormat;
use App\CustomerItemLog;
use App\BoxTab;
use App\BoxLog;
use App\BoxInventory;
use App\Notification;
use App\MasterChoiceItems;
use App\Logic\Replacements;
use App\Logic\MessageCenter;
use App\Logic\Payment\Billing;
use App\Jobs\CreateItemExport;
use App\Logic\NotificationService;
use Storage;
use App\Carrier;

class ImportExportController extends Controller
{

 private $shippingfields=[
                            'first_name' => ['First Name', 'required'],
                            'last_name' => ['Last Name', 'required'],
                            'email' => ['Email', 'required'],
                            'home_number' => ['Home Number', ''],
                            'mobile_number' => ['Mobile Number', 'required'],
                            'work_number' => ['Work Number', ''],
                            'spouse_number' => ['Spouse Number', ''],
                            'fax_number' => ['Fax Number', ''],
                            'street' => ['Address', 'required'],
                            'address2' => ['Address2', ''],
                            'city' => ['City', 'required'],
                            'state' => ['State', 'required'],
                            'zip' => ["Zip", 'required'],
                            'country' => ['Country', 'required'],
                            'lot' => ['Lot', 'required'],
                            'description' => ['Title/Description', 'required'],
                            'bid_amount' => ['Bid Amount', 'required']
                        ];
                        
                        
                        
      private $shippingfieldsm=[
            'lot' => ['Lot', 'required', 'text'],
            'auction_date' => ['Auction Date', 'required', 'text'],
            'description' => ['Title/Description', 'required', 'text'],
            'bid_amount' => ['Bid Amount', 'required', 'number']
        ];
        
        
       private $shippingSaintFieldsType = [
            'first_name' => ['First Name', 'required', 'text'],
            'last_name' => ['Last Name', 'required', 'text'],
            'email' => ['Email', 'required', 'email'],
            'mobile_number' => ['Mobile Number', 'required', 'phone'],
            'street' => ['Address', 'required', 'text'],
            'address2' => ['Address2', '', 'text'],
            'city' => ['City', 'required', 'text'],
            'state' => ['State', 'required', 'text'],
            'zip' => ["Zip", 'required', 'text'],
            'country' => ["Country", 'required', 'select']
        ];

       private $shippingSaintFieldsimType = [
            'lot' => ['Lot', 'required', 'text'],
            'auction_date' => ['Auction Date', 'required', 'text'],
            'description' => ['Title/Description', 'required', 'text'],
            'bid_amount' => ['Bid Amount', 'required', 'number']
        ];
        
    /**
     * The MessageCenter instance.
     *
     * @var \App\Logic\MessageCenter
     */
    private $messageCenter;

    /**
     * The Replacements instance.
     *
     * @var \App\Logic\Replacements
     */
    private $replacements;

    /**
     * Holds notification sending logic.
     *
     * @var \App\Logic\NotificationService
     */
    private $notificationService;

    /**
     * The Billing instance.
     *
     * @var \App\Logic\Billing
     */
    private $billing;

    public function __construct(
        MessageCenter $messageCenter,
        Replacements $replacements,
        Billing $billing,
        NotificationService $notificationService
    )
    {
        $this->messageCenter = $messageCenter;
        $this->replacements = $replacements;
        $this->billing = $billing;
        $this->notificationService = $notificationService;

        $anonymousRoutes = [
            'payNow',
            'processPayment',
            'updateCustomerAddress',
            'paypalPayment'
        ];
        $this->middleware('auth', ['except' => $anonymousRoutes]);

        $this->middleware(function ($request, $next) {
            $this->checkCallReminderOne();
            $this->checkCallReminderOther(2);
            $this->checkCallReminderOther(3);
            $this->_locationGroup();
            return $next($request);
        }, ['except' => $anonymousRoutes]);
    }

    public function importExport(Request $request)
    {
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $userId = $this->getAllUsersIds();
        $admin = $request->user()->admin;

        $shipmentOrders = $admin->shipmentOrders()
            ->where('stage', Item::STATUS_IMPORT)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->withUnpaidCustomerItems($userId)
            ->with('items')
            ->latest()
            ->get();

        $importFormats = ImportFormat::whereIn('user_id', $userId)->where(['type'=>0])->get();
        $exportFormats = ExportFormat::whereIn('user_id', $userId)->get();
        $exportFile = CreateItemExport::getFile();

        $shippingSaintFields =$this->shippingSaintFieldsType;

        $shippingSaintFieldsim =$this->shippingSaintFieldsimType;

        $countries = \Config::get('settings.country');

        return view('importexport.importExport', compact(
            'shipmentOrders', 'importFormats', 'exportFormats', 'exportFile',
            'countries','shippingSaintFieldsim','shippingSaintFields'
        ));
    }

    public function downloadExcel($type, Request $request)
    {
        $userId = $this->getAllUsersIds();
        $inputData = $request->all();


        $formatedData = DB::table('items')
                        ->join('customers', 'customers.id', '=', 'items.customer_id')
                        ->select($inputData['format'])
                        ->whereIn('items.user_id', $userId)
                        ->get()->toArray();

        $formatedData = json_decode(json_encode((array) $formatedData), true);

        $format = $inputData['format'];
        $custom = $inputData['custom'];
        //print_r($inputData['custom']);
        //echo "<pre>";
        foreach ($formatedData as $k => $v) {
            foreach ($format as $ke => $val) {
                if (isset($custom[$ke])) {
                    $tmp = $formatedData[$k][$val];
                    unset($formatedData[$k][$val]);
                    $formatedData[$k][$custom[$ke]] = $tmp;
                }
            }
        }
        $formatName = $inputData['format_name'];
        if ($inputData['save_format']) {
            $custom = json_encode($inputData['custom']);

            $importFormatData = ['user_id' => Auth::user()->id, 'format' => json_encode($inputData['format']), 'custom' => $custom, 'name' => $formatName];
            ExportFormat::create($importFormatData);
        }
        return Excel::create('packingsaint', function ($excel) use ($formatedData) {
                    $excel->sheet('mySheet', function ($sheet) use ($formatedData) {
                        $sheet->fromArray($formatedData);
                    });
                })->download($type);
    }

    public function downloadAllData($type)
    {
        $userIds = $this->getAllUsersIds();

        // queue export process, this is handled by supervisord on the live site
        CreateItemExport::dispatch($userIds, $type);
    }

    /**
     * Check if export is finished. Returns filename or false.
     */
    public function exportFinished()
    {
        return ['finished' => CreateItemExport::getFile() ? true : false];
    }

    /**
     * Download export file from storage/exports/
     */
    public function downloadExportFile()
    {
        $file = CreateItemExport::getFile();
        return $file ? response()->download($file) : abort(404);
    }

    public function downloadExcelByFormat($type, $id)
    {
        $userIds = $this->getAllUsersIds();

        CreateItemExport::dispatch($userIds, $type, $id);
    }

    public function importExcel(Request $request, \App\Logic\PackingService $packing)
    {

        $inputData = $request->all();
        $items = [];
        if (!array_key_exists('map', $inputData)) {
            $validator = Validator::make($request->all(), [
                        'import_file' => 'required',
                        'auction_date' => 'required'
                            ], [
                        'import_file.required' => 'Please select a file',
                        'auction_date.required' => 'Auction date is required'
                            ]
            );

            if ($validator->fails()) {

                $this->throwValidationException(
                        $request, $validator
                );
            } else {
                $extension = $request->import_file->getClientOriginalExtension();
                $fileName = $request->import_file->getClientOriginalName();
                if (!in_array($extension, ['xls', 'xlsx'])) {
                    return back()->with('error', 'Please upload excel file only.');
                }

                $path = Input::file('import_file')->getRealPath();
                $data = Excel::load($path, function ($reader) {
                            
                        })->get();
                if ($data->count() > 0) {
                    if (!empty($inputData['import_format'])) {
                        $importFormat = ImportFormat::find($inputData['import_format']);
                        $importFormat = json_decode($importFormat->format, true);
                        $auctionDate = date('Y-m-d', strtotime($inputData['auction_date']));
                        $sender = $inputData['sender'];
                        foreach ($data as $excelValue) {
                            $excelValueArray = json_decode(json_encode($excelValue),true);
                            if (array_filter($excelValueArray)) {
                            $homeNumber = $workNumber = $spouseNumber = $faxNumber = null;
                            if ($importFormat['home_number'] != null) {
                                $homeNumber = $excelValue->{$importFormat['home_number']};
                            }

                            if ($importFormat['work_number'] != null) {
                                $workNumber = $excelValue->{$importFormat['work_number']};
                            }

                            if ($importFormat['spouse_number'] != null) {
                                $spouseNumber = $excelValue->{$importFormat['spouse_number']};
                            }

                            if ($importFormat['fax_number'] != null) {
                                $faxNumber = $excelValue->{$importFormat['fax_number']};
                            }

                            $customerDetail = [
                                'street' => $excelValue->{$importFormat['street']},
                                'city' => $excelValue->{$importFormat['city']},
                                'state' => $excelValue->{$importFormat['state']},
                                'zip' => $excelValue->{$importFormat['zip']},
                                'address2' => $excelValue->{$importFormat['address2']},
                                'country' => $excelValue->{$importFormat['country']},
                                'first_name' => $excelValue->{$importFormat['first_name']},
                                'last_name' => $excelValue->{$importFormat['last_name']},
                                'mobile_number' => $excelValue->{$importFormat['mobile_number']},
                                'email' => $excelValue->{$importFormat['email']},
                                'home_number' => $homeNumber,
                                'work_number' => $workNumber,
                                'spouse_number' => $spouseNumber,
                                'fax_number' => $faxNumber
                            ];

                            $customer = Customers::where('email', '=', $excelValue->{$importFormat['email']})->get();
                            if ($customer->count() > 0) {
                                $customerId = $customer->first()->id;
                                if(empty($customer->first()->mobile_number)){
                                    Customers::where('id', $customer->first()->id)->update(['mobile_number'=>$customerDetail['mobile_number']]);
                                }
                            } else {
                                $customer = Customers::create($customerDetail);
                                $customerId = $customer->id;
                            }

                            $lot = $title = $bidAmount = null;

                            if (strpos($excelValue->{$importFormat['lot']}, ":") !== false) {
                                $lotDetail = explode(':', $excelValue->{$importFormat['lot']});
                                $lot = $lotDetail[0];
                                $description = $lotDetail[1];
                            } else if (strpos($excelValue->{$importFormat['description']}, ":") !== false) {
                                $lotDetail = explode(':', $excelValue->{$importFormat['description']});
                                $lot = $lotDetail[0];
                                $description = $lotDetail[1];
                            } else {
                                $lot = $excelValue->{$importFormat['lot']};
                                $description = $excelValue->{$importFormat['description']};
                            }
                                if ($importFormat['bid_amount'] == 'sale_price') {
                                    $bidAmount = (str_replace('$', '', $excelValue->{$importFormat['bid_amount']}) + str_replace('$', '', $excelValue->buyer_premium));
                                } else {
                                    $bidAmount = str_replace('$', '', $excelValue->{$importFormat['bid_amount']});
                                }
                                if (empty($bidAmount) &&
                                    isset($excelValue->sale_price) &&
                                    isset($excelValue->buyer_premium)
                                ) {
                                    $bidAmount = (float)trim($excelValue->sale_price, '$') +
                                        (float)trim($excelValue->buyer_premium, '$');
                                }
                                $userId = Auth::user()->id;

                                /* check if item already exists */
                                $startDate = date('Y-m-d', strtotime('-3 days', strtotime($auctionDate)));
                                $endDate = date('Y-m-d', strtotime('+4 days', strtotime($auctionDate)));
                                $dateRange = createDateRange($startDate, $endDate);
                                $checkItem = Item::where(['customer_id' => $customerId, 'lot' => $lot, 'user_id' => $userId, 'description' => $description])
                                                ->whereIn('auction_date', $dateRange)->get();
                                if ($checkItem->count()) {
                                    continue;
                                }
                                /* check if item already exists */
                                $insert = ['auction_date' => $auctionDate, 'customer_id' => $customerId, 'user_id' => $userId, 'lot' => $lot, 'description' => $description, 'bid_amount' => $bidAmount, 'sender' => $sender];
                                $items[] = Item::create($insert);
                            }
                        }
                        $packing->checkForSameCustomers();
                        ShipmentOrder::createOrdersForItems($items);
                        return redirect('/importExport')->with('success', 'Data imported successfully');
                    } else {
                        $auctionDate = $inputData['auction_date'];
                        $shippingSaintFields = $this->shippingfields;
                        $excelFields = $data->first()->keys()->toArray();
                        $excelData = json_encode($data);
                        return view('importexport.mapData', compact('auctionDate', 'shippingSaintFields', 'excelFields', 'excelData', 'fileName'));
                    }
                } else {
                    return back()->with('error', 'Uploaded file is empty');
                }
                $packing->checkForSameCustomers();
                ShipmentOrder::createOrdersForItems($items);
                return back()->with('success', 'Data imported successfully');
            }
        } else {
            $excelData = json_decode($inputData['data']);
            $formatName = $inputData['format_name'] != '' ? $inputData['format_name'] : $inputData['file_name'];
            unset($inputData['data']);
            unset($inputData['_token']);
            unset($inputData['format_name']);
            $auctionDate = date('Y-m-d', strtotime($inputData['auction_date']));

            foreach ($excelData as $excelValue) {
                $excelValueArray = json_decode(json_encode($excelValue),true);
                if (array_filter($excelValueArray)) {
                $homeNumber = $workNumber = $spouseNumber = $faxNumber = null;
                if ($inputData['home_number'] != null) {
                    $homeNumber = $excelValue->{$inputData['home_number']};
                }

                if ($inputData['work_number'] != null) {
                    $workNumber = $excelValue->{$inputData['work_number']};
                }

                if ($inputData['spouse_number'] != null) {
                    $spouseNumber = $excelValue->{$inputData['spouse_number']};
                }

                if ($inputData['fax_number'] != null) {
                    $faxNumber = $excelValue->{$inputData['fax_number']};
                }
                if ($inputData['address2'] != null) {
                    @$address2 = $excelValue->{$inputData['address2']};
                } else {
                    $address2 = '';
                }

                if ($inputData['country'] != null) {
                    @$country = $excelValue->{$inputData['country']};
                } else {
                    $country = '';
                }

                $customerDetail = [
                    'street' => $excelValue->{$inputData['street']},
                    'city' => $excelValue->{$inputData['city']},
                    'state' => $excelValue->{$inputData['state']},
                    'zip' => $excelValue->{$inputData['zip']},
                    'address2' => $address2,
                    'country' => $country,
                    'first_name' => $excelValue->{$inputData['first_name']},
                    'last_name' => $excelValue->{$inputData['last_name']},
                    'mobile_number' => $excelValue->{$inputData['mobile_number']},
                    'email' => $excelValue->{$inputData['email']},
                    'home_number' => $homeNumber,
                    'work_number' => $workNumber,
                    'spouse_number' => $spouseNumber,
                    'fax_number' => $faxNumber];

                $customer = Customers::where('email', '=', $excelValue->{$inputData['email']})->get();
                if ($customer->count() > 0) {
                    $customerId = $customer->first()->id;
                    if(empty($customer->first()->mobile_number)){
                        Customers::where('id', $customer->first()->id)->update(['mobile_number'=>$customerDetail['mobile_number']]);
                    }
                } else {
                    $customer = Customers::create($customerDetail);
                    $customerId = $customer->id;
                }

                $lot = $title = $bidAmount = null;

                if (strpos($excelValue->{$inputData['lot']}, ":") !== false) {
                    $lotDetail = explode(':', $excelValue->{$inputData['lot']});
                    $lot = $lotDetail[0];
                    $description = $lotDetail[1];
                } else if (strpos($excelValue->{$inputData['description']}, ":") !== false) {
                    $lotDetail = explode(':', $excelValue->{$inputData['description']});
                    $lot = $lotDetail[0];
                    $description = $lotDetail[1];
                } else {
                    $lot = $excelValue->{$inputData['lot']};
                    $description = $excelValue->{$inputData['description']};
                }

                if ($inputData['bid_amount'] == 'sale_price') {
                    $bidAmount = (str_replace('$', '', $excelValue->{$inputData['bid_amount']}) + str_replace('$', '', $excelValue->buyer_premium));
                } else {
                    $bidAmount = str_replace('$', '', $excelValue->{$inputData['bid_amount']});
                }
                $userId = Auth::user()->id;
                /* check if item already exists */
                $startDate = date('Y-m-d', strtotime('-3 days', strtotime($auctionDate)));
                $endDate = date('Y-m-d', strtotime('+4 days', strtotime($auctionDate)));
                $dateRange = createDateRange($startDate,$endDate);
                $checkItem = Item::where(['customer_id'=>$customerId,'lot'=>$lot,'user_id' => $userId,'description'=>$description])
                        ->whereIn('auction_date',$dateRange)->get();
                if($checkItem->count()){
                   continue; 
                }
                /* check if item already exists */
                $insert = ['auction_date' => $auctionDate, 'customer_id' => $customerId, 'user_id' => $userId, 'lot' => $lot, 'description' => $description, 'bid_amount' => $bidAmount];
                $items[] = Item::create($insert);
            }
            }

            if ($inputData['save_format']) {
                $importFormatData = ['user_id' => Auth::user()->id, 'format' => json_encode($inputData), 'name' => $formatName];
                ImportFormat::create($importFormatData);
            }
            $packing->checkForSameCustomers();
            ShipmentOrder::createOrdersForItems($items);
            return redirect('/importExport')->with('success', 'Data imported successfully');
        }
    }

    public function editImport($id = null)
    {
        $ids = base64_decode($id);
        $id = max(explode(',', $ids));
        $item = Item::with('customers')->findOrFail($id);
        if (!empty($item->auction_date)) {
            $item->auction_date = date('m/d/Y', strtotime($item->auction_date));
        }
        $data = [
            'item' => $item,
            'ids' => $ids
        ];
        return view('importexport.edit_item')->with($data);
    }

    public function update_item(Request $request, $id)
    {
        $id = base64_decode($id);
        $item = Item::find($id);
        $validator = Validator::make($request->all(), [
                    'auction_date' => 'required|date_format:"m/d/Y"|before:' . date('m/d/Y'),
                        ], [
                    'auction_date.date_format' => 'Please enter a valid date',
                        ]
        );

        if ($validator->fails()) {
            $this->throwValidationException(
                    $request, $validator
            );
        } else {

            DB::table('items')
                    ->whereIn('id', explode(',', $request->input('ids')))
                    ->update(array('auction_date' => date('Y-m-d', strtotime($request->input('auction_date')))));

            return redirect('/importExport')->with('success', trans('Item updated successfully'));
        }
    }

    public function moveItems(Request $request)
    {
        $data = $request->all();
        if (empty($data['step']) && empty($data['location']) && empty($data['group'])) {
            return back()->with('error', 'Please select move to or location or group.');
        }

        if (isset($data['items']) && !empty($data['items'])) {
            $itemIds = [];
            foreach ($data['items'] as $item) {
                $itemIds = array_merge($itemIds, explode(',', base64_decode($item)));
                if ($data['step'] === 3) {
                    $this->_initiateNotification($item, $data['step']);
                }
            }
            $items = $request->user()->admin->items()->with('shipmentOrder')->find($itemIds);

            foreach ($items as $itemData) {
                if (!empty($data['location'])) {
                    $itemData->location = $data['location'];
                    $itemData->save();
                }

                if (!empty($data['group'])) {
                    $itemData->tray = $data['group'];
                    $itemData->save();
                }

                if ($data['step'] == "delete") {
                    $itemData->delete();
                } else {
                    $logNotes = '';
                    if (!empty($data['step']) && isset($data['notes'])) {
                        $logNotes = $data['notes'][$itemData->id];
                    } else if (isset($data['notes'])) {
                        $itemData->notes = $data['notes'][$itemData->id];
                        $itemData->save();
                    }

                    if (!empty($data['step'])) {
                        $itemData->status = $data['step'];
                        $itemData->save();
                        $itemData->shipmentOrder->stage = $data['step'];
                        $itemData->shipmentOrder->save();
                        ItemLog::create([
                            'user_id' => Auth::user()->id,
                            'item_id' => $itemData->id,
                            'move_date' => date('Y-m-d H:i:s'),
                            'step_moved' => $data['step'],
                            'notes' => $logNotes
                        ]);
                    }
                }
            }
            if (!empty($data['step'])) {
                return back()->with('success', 'Item(s) have been moved successfully');
            } else {
                return back()->with('success', 'Location/Tray as been assigned to item(s).');
            }
        } else {
            return back()->with('error', 'Please select at least one item ');
        }
    }

    public function prepareShip(Request $request)
    {
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $userId = $this->getAllUsersIds();

        $shipmentOrders = $request->user()->admin->shipmentOrders()
            ->where('stage', Item::STATUS_PREPARE_TO_SHIP)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->withUnpaidCustomerItems($userId)
            ->with('items.groups', 'items.locations', 'items._notes')
            ->latest()
            ->get();
        $this->_locationGroup();

        return view('importexport.prepareShip', compact('shipmentOrders'));
    }

    public function readyToPack(Request $request)
    {
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $userId = $this->getAllUsersIds();

        $shipmentOrders = $request->user()->admin->shipmentOrders()
            ->where('stage', Item::STATUS_READY_TO_PACK)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->withUnpaidCustomerItems($userId)
            ->with('items.groups', 'items.locations', 'items._notes')
            ->with(['items.logs' => function($q) { $q->where('step_moved', 3); }])
            ->latest()
            ->get();

        return view('importexport.packReady', compact('shipmentOrders'));
    }

    /* common function to get locations and group and assigning to view */

    public function _locationGroup()
    {
        view()->composer('*', function ($view) {
            $userId = $this->getAllUsersIds();
            $locations = Location::whereIn('user_id', $userId)->whereNotIn('status', [0, 2])->get();
            $groups = Group::whereIn('user_id', $userId)->whereNotIn('status', [0, 2])->get();
            $view->with('locations', $locations);
            $view->with('groups', $groups);
        });
    }

    /* common function to initiate email */

    public function _initiateNotification($items, $step)
    {
    	
        $item = max(explode(',', base64_decode($items)));
        $steps = [3 => 'ready_to_pack'];
        $userId = $this->getAllUsersIds();
        $itemDetail = Item::where('id', '=', $item)->with('customers')->get();
        $itemDetail = $itemDetail->first();
        if($itemDetail->shipping_paid_byme==1){
			return false;
		}
        $emailTemplate = UserEmailTemplate::where('slug', '=', $steps[$step])->whereIn('user_id', $userId)->get();
        if ($emailTemplate->count() == 0) {
            $emailTemplate = EmailTemplate::where('slug', '=', $steps[$step])->get();
        }
        $emailTemplate = $emailTemplate->first();
        $AppSetting = AppSetting::whereIn('user_id', $userId)->get()->first();
        $this->replacements->setItems([$itemDetail])->addData('settings', $AppSetting);
        $reply = is_null($AppSetting) ? null : $AppSetting->reply_to;
        $to = $itemDetail->customers->email;
        $subject = $this->replacements->replace($emailTemplate->subject);
        $emailContent = $this->replacements->replace($emailTemplate->content);
        $smsContent = $this->replacements->replace($emailTemplate->sms);

        if ($emailTemplate->send_email) {
            $this->_sendMail($to, $subject, $emailContent, $reply);
        }
        if ($emailTemplate->send_sms && !empty($emailTemplate->sms)) {
            $result = $this->messageCenter
                    ->setItem($itemDetail)
                    ->setAdmin(\Auth::user()->admin)
                    ->setMessage($smsContent)
                    ->send();
        }
    }

    /* common function to send mail */

    public function _sendMail($to, $subject, $content, $replyTo = null)
    {
        $devs = [
            'pgibson78@gmail.com',
            'ayush11ramola@gmail.com',
            'dhirendra1123@gmail.com',
            'abcd@gmail.com'
        ];
        if (in_array(Auth::user()->email, $devs)) {
            $to = 'ayush11@mailinator.com';
        }
        Mail::to($to)->queue(new \App\Mail\Generic($subject, $content, $replyTo));
    }

    /* get tray detail */

    public function trayDetail()
    {
        $this->_locationGroup();
        $dataToggle = Input::get('c');
        $ids = explode(',', base64_decode(Input::get('c')));
        $check = Input::get('d');
        if ($check == 'process') {
            $this->_locationGroup();
            $prepareShipData = Item::where('status', '=', 4)->whereIn('id', $ids)->with(['customers' => function ($query) {
                            $query->select('id','first_name', 'street', 'last_name', 'email', 'mobile_number','city', 'state', 'zip');
                        },
                        'groups' => function ($query) {
                            $query->select('id', 'group');
                        },
                        'locations' => function ($query) {
                            $query->select('id', 'location');
                        },
                        'logs' => function ($query) {
                            $query->select('*')->where('step_moved', '=', 5)->orWhere('step_moved', '=', 4)->orderBy('move_date', 'desc');
                        }, 'logs.user', 'package'])->orderBy('created_at', 'desc')->get();

            $packages = $prepareShipData->pluck('package')->unique()->filter(function($package) {
                return ! is_null($package);
            });
            $userId = $this->getAllUsersIds();
            $AppSetting = AppSetting::whereIn('user_id', $userId)->get()->first();

            /* carrier list  */
            array_push($userId,0);
            $carriers = Carrier::whereIn('user_id', $userId)->get();

            $html = View('importexport.shippingForm', compact(
                'prepareShipData', 'packages', 'AppSetting', 'carriers'
            ))->render();
            return response()->json(array('success' => true, 'html' => $html));
        } else if ($check == 'view') {
            $prepareShipData = Item::where('status', '=', Input::get('step'))->whereIn('id', $ids)->with(['customers'])->orderBy('created_at', 'desc')->get();
            $html = View('importexport.itemsDetail', compact('prepareShipData'))->render();
            return response()->json(array('success' => true, 'html' => $html));
        } else if ($check == 'ship') {
            $ids = implode(',', $ids);
            $html = View('importexport.trackingDetailForm', compact('ids'))->render();
            return response()->json(array('success' => true, 'html' => $html));
        } else if ($check == 'shipping_view') {
            $prepareShipData = Item::where('status', '=', 6)
                ->whereIn('id', $ids)
                ->with(['customers', 'locations', 'groups', 'logs', 'package'])
                ->orderBy('created_at', 'desc')->get();
            $packages = $prepareShipData->pluck('package')->unique();

            $userId = $this->getAllUsersIds();
            $AppSetting = AppSetting::whereIn('user_id', $userId)->get()->first();
            $ids = implode(',', $ids);
            $html = view(
                'importexport.shippingView',
                compact('prepareShipData', 'AppSetting', 'ids', 'packages')
            )->render();
            return response()->json(array('success' => true, 'html' => $html));
        } else {
            $html = \App\Logic\PackingService::packingDetails(Auth::user(), $ids[0]);
            return response()->json(array('success' => true, 'html' => $html));
        }
    }

    /* save tray details */

    public function saveTrayDetails(Request $request)
    {
        $data = $request->all();
        if (isset($data['status']) && !empty($data['status'])) {
            $validator = Validator::make($request->all(), Item::packingRules(), Item::packingRuleMessages());
            if ($validator->fails()) {
                return Response::json(array('fail' => true, 'errors' => $validator->getMessageBag()->toArray()));
            }
        }
        Package::updatePackagesFromReadyToPack($request);

        $ids = array_filter(explode(',', $data['id']));
        $data['packing_time'] = gmdate("H:i:s", ($data['packing_time'] - 1));

        $skipStep = $request->has('packages') ? 1 : 0;

        // if all packages have shipping rates move to waiting for payment
        // otherwise move to processing/weigh
        foreach ($request->input('packages', []) as $id => $package) {
            if (empty($package['shipping_cost']) || $package['shipping_cost'] === '0.00') {
                $skipStep = 0;
            }
        }
        $invoiceNumber = $this->_getTrackingNumber();
        $items = Item::with('shipmentOrder')->find($ids);

        foreach ($items as $item) {
            $item->packing_time = $data['packing_time'];
            $item->box_price = $data['box_price'] ?? 0;
            $item->packing_price = $data['packing_price'] ?? 0;
            $item->notes = $data['notes'] ?? '';
            $item->packing_time_price = $data['packing_time_price'];
            $item->location = $data['location'] ?? 0;
            $item->total_packing_price = $item->box_price + $item->packing_price;
            $item->total_price = $data['box_price'] + $data['packing_price'] + $item->shipping_price;

            if (isset($data['status']) && !empty($data['status'])) {
                $item->status = $data['status'];
                if ($skipStep == 1) {
                    $item->item_number = $invoiceNumber;
                    $item->status = $data['status'] + 1;
                }
                $logData = ['user_id' => Auth::user()->id, 'item_id' => $item->id,'from_stage' =>3, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => $item->status, 'notes' => $data['notes']];
                ItemLog::create($logData);
                $temp['lot'] = $item->lot;
                $temp['id'] = $item->id;
                $temp['description'] = $item->description;
                $temp['bid_amount'] = $item->bid_amount;
            }
            $item->save();

            /* save register details used in packing */
            if (isset($data['box_used']) && !empty($data['box_used']) && in_array($item->id, $itemsPacked)) {
                foreach ($data['box_used'] as $box) {
                    $boxDetail = explode('_', $box);
                    $createBox = ['group' => $data['box_group'], 'box_id' => $boxDetail[0], 'item_id' => $item->id, 'quantity' => $boxDetail[1], 'cost' => $boxDetail[2], 'type' => $boxDetail[3]];
                    BoxLog::create($createBox);
                }
            }
        }
        // update stage on ShipmentOrders
        foreach ($items->pluck('shipmentOrder')->unique('id') as $order) {
            $order->stage = $order->items[0]->status;
            $order->save();
        }
        /* save image taken of packages */
        $itemId = max($ids);
        if (isset($data['image']) && !empty($data['image'])) {
            $upload_dir = public_path('/img/package_images/');
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777);
            }
            foreach ($data['image'] as $image) {
                $filteredData = substr($image, strpos($image, ",") + 1);
                $unencodedData = base64_decode($filteredData);
                $imageName = $itemId . "_" . round(microtime(true) * 1000) . '.jpg';
                $fp = fopen($upload_dir . $imageName, 'wb');
                fwrite($fp, $unencodedData);
                fclose($fp);
                $imageData = ['item_id' => $itemId, 'image' => $imageName];
                ItemImage::create($imageData);
            }
        }

        /* update register inventory after using in a package */
        if (isset($data['box_used']) && !empty($data['box_used'])) {
            foreach ($data['box_used'] as $box) {
                $boxDetail = explode('_', $box);
                BoxInventory::where('id', '=', $boxDetail[0])->decrement('stock', $boxDetail[1]);
            }
        }

        /* send email if skipping process stage */
        if ($skipStep && isset($data['status']) && !empty($data['status'])) {
            $this->_initiatePaymentNotification($items);
        }

        return [
            'success' => true,
            'next_stage' => $skipStep ? 'totalPaymentCount' : 'totalProcessCount',
            'message' => 'Items updated successfully.'
        ];
    }

    /* delete items images */

    public function deleteItemImages()
    {
        $data = Input::get('d');
        $id = base64_decode($data);
        $itemImage = ItemImage::find($id);
        $image = $itemImage->image;
        $itemImage->delete();
        @unlink(public_path('/img/package_images/' . $image));
        return Response::json(array(
                    'success' => true,
                    'message' => 'Items updated successfully.'
        ));
    }

    /* move item to not shipping from ready to pack step */

    public function moveItemsFromPack(Request $request)
    {
        $data = $request->all();
        $admin = $request->user()->admin;

        if (isset($data['items']) && !empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $item = base64_decode($item);
                $ids = explode(',', $item);
                if (!empty($data['step'])) {
                    $items = $admin->items()->find($ids);
                    Item::whereIn('id', $items->pluck('id'))->update([
                        'status' => $data['step'],
                        'notes' => $data['notes'][$item] ?? NULL,
                    ]);
                    ShipmentOrder::whereIn('id', $items->pluck('shipment_order_id'))
                        ->update(['stage' => $data['step']]);

                    foreach ($ids as $id) {
                        $logData = ['user_id' => Auth::user()->id, 'item_id' => $id, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => $data['step'], 'notes' => (isset($data['notes'])) ? $data['notes'][$item] : NULL];
                        ItemLog::create($logData);
                    }
                }
            }
            return back()->with('success', 'Item(s) have been moved successfully');
        } else {
            return back()->with('error', 'Please select at least one item ');
        }
    }

    /* step 4 Processing Weigh */

    public function processingWeigh(Request $request)
    {
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $userId = $this->getAllUsersIds();

        $shipmentOrders = $request->user()->admin->shipmentOrders()
            ->where('stage', Item::STATUS_PROCESSING)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->withUnpaidCustomerItems($userId)
            ->with('items.groups', 'items.locations', 'items._notes')
            ->with(['items.logs' => function($query) {
                $query->where('step_moved', Item::STATUS_PROCESSING)
                    ->orderBy('move_date', 'desc');
            }])
            ->get();

        return view('importexport.weighProcessing', compact('shipmentOrders'));
    }

    public function packagesToSell(Request $request)
    {
        $userId = $this->getAllUsersIds();
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';

        $shipmentOrders = $request->user()->admin->shipmentOrders()
            ->where('stage', Item::STATUS_PACKAGES_TO_SELL)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->with('customer', 'items.groups', 'items.locations', 'items._notes')
            ->with('items.box_logs.box', 'packages.box')
            ->with(['items.logs' => function($query) {
                $query->where('step_moved', Item::STATUS_PACKAGES_TO_SELL)
                    ->orderBy('move_date', 'desc');
            }])
            ->get();

        $userId = $this->getAllUsersIds();
        $appSetting = AppSetting::whereIn('user_id', $userId)->get()->first();

        return view('importexport.packagesToSell', compact('shipmentOrders', 'appSetting'));
    }

    /* save tray details */

    public function saveShippingDetails(Request $request)
    {
        $this->validate($request, [
            'packages.*.shipping_cost' => 'required|numeric|min:1.00',
            'packages.*.insurance_cost' => 'nullable|regex:/^\d*(\.\d{1,5})?$/',
            'packages.*.width' => 'required|regex:/^\d*(\.\d{1,2})?$/',
            'packages.*.length' => 'required|regex:/^\d*(\.\d{1,2})?$/',
            'packages.*.height' => 'required|regex:/^\d*(\.\d{1,2})?$/',
            'packages.*.weight' => 'required|regex:/^\d*(\.\d{1,2})?$/',
            'packages.*.carrier' => 'required',
        ]);
        Package::updatePackagesFromReadyToPack($request);
        $admin = $request->user()->admin;
        $invoiceNumber = $this->_getTrackingNumber();
        $data = $request->all();
        $items = $admin->items()
            ->with('shipmentOrder')
            ->find(array_filter(explode(',', $data['id'])));
        $itemsArray = [];
        $logs = [];
        foreach ($items as $item) {
            $item->shipping_price = $data['shipping_price'];
            $item->total_price = $item->total_packing_price + $data['shipping_price'];
            $item->item_number = $invoiceNumber;
            $item->insurance_price = $data['insurance_cost'];
            $item->carrier_used = $data['carrier_used'];
            $item->shipping_option = $data['shipping_option'];
            $item->location = $data['location'] != null ? $data['location'] : 0;
            $item->notes = $data['notes'];
            $item->status = $data['status'];
            $item->save();
            $logs[] = [
                'item_id' => $item->id,
                'move_date' => date('Y-m-d H:i:s'),
                'step_moved' => $data['status'],
                'notes' => $data['notes']
            ];
            $temp['lot'] = $item->lot;
            $temp['id'] = $item->id;
            $temp['description'] = $item->description;
            $temp['bid_amount'] = $item->bid_amount;
            $temp['shipping_price'] = $item->shipping_price;
            $temp['insurance_price'] = $item->insurance_price;
            $temp['total_packing_price'] = $item->total_packing_price;
            array_push($itemsArray, $temp);
        }
        $admin->itemLogs()->createMany($logs);
        $this->_initiatePaymentNotification($itemsArray);

        // update stage on ShipmentOrders
        foreach ($items->pluck('shipmentOrder')->unique('id') as $order) {
            $order->stage = $data['status'];
            $order->save();
        }
        return Response::json(array(
                    'success' => true,
                    'message' => 'Items updated sucessfully.'
        ));
    }

    /* save tray details */

    public function resendInvoicesFromTask(Request $request)
    {
        $validator = Validator::make($request->all(), Item::shippingRules(), Item::shippingRuleMessages()
        );
        if ($validator->fails()) {
            return Response::json(array(
                        'fail' => true,
                        'errors' => $validator->getMessageBag()->toArray()
            ));
        }
        $invoiceNumber = $this->_getTrackingNumber();
        $data = $request->all();
        $ids = array_filter(explode(',', $data['id']));
        $itemsArray = [];
        foreach ($ids as $id) {
            $item = Item::find($id);
            $item->shipping_price = $data['shipping_price'];
            $item->total_price = $item->total_packing_price + $data['shipping_price'];
            $item->length = $data['length'];
            $item->item_number = $invoiceNumber;
            $item->width = $data['width'];
            $item->height = $data['height'];
            $item->weight = $data['weight'];
            $item->weightunit = $data['weightunit'];
            $item->insurance_price = $data['insurance_price'];
            $item->carrier_used = $data['carrier_used'];
            $item->shipping_option = $data['shipping_option'];
            $item->location = $data['location'] != null ? $data['location'] : 0;
            $item->notes = $data['notes'];
            $item->status = $data['status'];
            $item->save();

            $logData = ['user_id' => Auth::user()->id, 'item_id' => $id, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => $data['status'], 'notes' => $data['notes']];
            ItemLog::create($logData);
            $temp['lot'] = $item->lot;
            $temp['id'] = $item->id;
            $temp['description'] = $item->description;
            $temp['bid_amount'] = $item->bid_amount;
            $temp['shipping_price'] = $item->shipping_price;
            $temp['insurance_price'] = $item->insurance_price;
            $temp['total_packing_price'] = $item->total_packing_price;
            array_push($itemsArray, $temp);
        }
        $this->_initiatePaymentNotification($itemsArray);
        return Response::json(array(
                    'success' => true,
                    'message' => 'Items updated sucessfully.'
        ));
    }

    /* function to get tracking number */

    public function _getTrackingNumber()
    {
        $currentDate = date('Y-m-d');
        $firstDate = date('Y-m-01', strtotime($currentDate));
        $lastDate = date('Y-m-t', strtotime($currentDate));
        $items = Item::whereHas('logs', function($q) use ($firstDate, $lastDate) {
                    $q->where('step_moved', '=', 5)->whereBetween('move_date', [$firstDate, $lastDate]);
                })->select(DB::raw('count(*) as `total`'), 'customer_id')->groupBy('auction_date', 'customer_id')
                ->get();
        $itemLogs = $items->count() ? $items->count() : $items->count() + 1;
        $itemLogs = date('ym') . str_pad($itemLogs, 4, '0', STR_PAD_LEFT);
        $invoiceNumber = $this->_getTrackingNumberItemTable($itemLogs);
        return $invoiceNumber;
    }

    public function _getTrackingNumberItemTable($itemLogs)
    {
        $invoiceNumber = Item::where('item_number', '=', $itemLogs)->get();
        if ($invoiceNumber->count() > 0) {
            $itemLogs = $itemLogs + 1;
            $this->_getTrackingNumberItemTable($itemLogs);
        }
        return $itemLogs;
    }

    /* set up email for packing + shipping payment and info */

    public function _initiatePaymentNotification($items, $logMessage = 'Auto email sent to customer')
    {
        $items = Item::with('customers')
                ->whereIn('id', array_pluck($items, 'id'))
                ->get();
          $itemDetail =Item::with('customers')
                ->whereIn('id', array_pluck($items, 'id'))
                ->first();
        if($itemDetail->shipping_paid_byme==1){
			return false;
		}
		
        $userId = $this->getAllUsersIds();
        $AppSetting = AppSetting::whereIn('user_id', $userId)->get()->first();
        // set replacement data
        $this->replacements->setItems($items)->addData('settings', $AppSetting);
        /* get email template */
        $emailTemplate = UserEmailTemplate::where('slug', '=', 'waiting_for_payment')->whereIn('user_id', $userId)->get();
        if ($emailTemplate->count() == 0) {
            $emailTemplate = EmailTemplate::where('slug', '=', 'waiting_for_payment')->get();
        }
        $emailTemplate = $emailTemplate->first();
        // replace tokens
        $emailContent = $this->replacements->replace($emailTemplate->content);
        $smsContent = $this->replacements->replace($emailTemplate->sms);

        /* send email */
        if ($emailTemplate->send_email) {
            $reply = is_null($AppSetting) ? null : $AppSetting->reply_to;
            $this->_sendMail($items[0]->customers->email, $emailTemplate->subject, $emailContent, $reply);
        }
        /* send sms */
        if ($emailTemplate->send_sms && !empty($emailTemplate->sms)) {
            $result = $this->messageCenter
                    ->setItem($items[0])
                    ->setAdmin(\Auth::user()->admin)
                    ->setMessage($smsContent)
                    ->send();
        }
        /* email log */
        $customerItemLog = CustomerItemLog::create([
                    'customer_id' => $items[0]->customer_id,
                    'item_id' => $items->max('id'),
                    'contact_medium' => 'Auto Email',
                    'contact_time' => date('Y-m-d H:i:s'),
                    'contact_details' => $items[0]->customers->email,
                    'result' => $logMessage,
                    'note' => 'Waiting For Payment Email'
        ]);
        $customerItemLog->save();

        // bill user for sending invoice
        $billInfo = $this->billing->setUser(\Auth::user()->admin)
                ->billForInvoice($items);
    }

    /* set up email for packing + shipping payment and info */

    public function _initiatePaymentNotifications($items, $type)
    {
        $itemId = $items[0]['id'];
        $items = Item::with('customers')
                ->whereIn('id', array_pluck($items, 'id'))
                ->get();
        $itemDetail = Item::where('id', '=', $itemId)->with('customers')->get();
        $itemDetail = $itemDetail->first();
        if($itemDetail->shipping_paid_byme==1){
			return false;
		}
        $userId = $this->getAllUsersIds();
        /* get email template */
        $emailTemplate = UserEmailTemplate::where('slug', '=', 'waiting_for_payment_' . $type)->whereIn('user_id', $userId)->get();
        if ($emailTemplate->count() == 0) {
            $emailTemplate = EmailTemplate::where('slug', '=', 'waiting_for_payment_' . $type)->get();
        }
        $emailTemplate = $emailTemplate->first();

        /* prepare email content */
        $AppSetting = AppSetting::whereIn('user_id', $userId)->get()->first();
        $this->replacements->setItems($items)->addData('settings', $AppSetting);
        /* send email */
        $to = $itemDetail->customers->email;
        $subject = $this->replacements->replace($emailTemplate->subject);
        $emailContent = $this->replacements->replace($emailTemplate->content);
        $smsContent = $this->replacements->replace($emailTemplate->sms);
        $reply = is_null($AppSetting) ? null : $AppSetting->reply_to;

        if ($emailTemplate->send_email) {
            $this->_sendMail($to, $subject, $emailContent, $reply);
        }
        if ($emailTemplate->send_sms && !empty($emailTemplate->sms)) {
            $result = $this->messageCenter
                    ->setItem($itemDetail)
                    ->setAdmin(\Auth::user()->admin)
                    ->setMessage($smsContent)
                    ->send();
        }

        /* email log */
        $customerItemLog = CustomerItemLog::create([
                    'customer_id' => $itemDetail->customer_id,
                    'item_id' => max(explode(',', $itemIds)),
                    'contact_medium' => 'Auto Email',
                    'contact_time' => date('Y-m-d H:i:s'),
                    'contact_details' => $itemDetail->customers->email,
                    'result' => 'Auto email sent to customer',
                    'note' => 'Waiting For Payment Email'
        ]);
        $customerItemLog->save();
    }

    public function paymentPending(Request $request)
    {
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $userId = $this->getAllUsersIds();

        $prepareShipData = $request->user()->admin->shipmentOrders()
            ->where('stage', Item::STATUS_PAYMENT_PENDING)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->with([
                'items.groups', 'items.locations', 'items._notes', 'items.package',
                'items.contact_logs', 'customer.items', 'customerItemLogs'
            ])
            ->with(['items.logs' => function($query) {
                $query->where('step_moved', 5)->orderBy('move_date', 'desc');
            }])
            ->withUnpaidCustomerItems($userId)
            ->latest()->get();

        return view('importexport.paymentPending', compact('prepareShipData'));
    }

    /* function to redirect users to paypal for payment */

    public function payNow(\App\Logic\Payment\Stripe $stripe)
    {
        $ids = Input::get('token');
        $payNowId = Input::get('paynow');
        if (!empty($payNowId)) {
            $query = explode(',', base64_decode($payNowId));
            $byAppUser = 1;
        }
        if (!empty($ids)) {
            $query = explode(',', str_replace('PP', '', base64_decode($ids)));
            $byAppUser = 0;
        }

        $shipmentOrder = Item::whereIn('id', $query)->first()->shipmentOrder
            ->load('items', 'packages', 'customer', 'user');

        $itemsDetail = $payNowData = $shipmentOrder->items;
        if ($shipmentOrder->stage === 5) {
            $commonId = $shipmentOrder->user->cid;
            $userIds = User::select('id')->where('cid', '=', $commonId)->get();
            $outputIds = array();
            $adminUser = User::find($commonId);
            foreach ($userIds as $output) {
                array_push($outputIds, $output->id);
            }
            $userSetting = AppSetting::whereIn('user_id', $outputIds)->get();
            $userSetting = $userSetting->first() ?: new AppSetting;
            $processor = 'stripe';
            $stripe->setPayee($adminUser);
            $countries = \Config::get('settings.country');
            $subView = Input::get('subView');
            $customer = $shipmentOrder->customer;
            $packages = $shipmentOrder->packages;

            return view('importexport.payNow', compact(
                'itemsDetail', 'payNowData', 'adminUser', 'query', 'byAppUser',
                'userSetting', 'processor', 'stripe', 'countries', 'subView',
                'customer', 'packages', 'shipmentOrder'
            ));
        } else {
            $payNowData = Item::whereIn('id', $query)
                ->with('customers', 'users', 'package')
                ->get();
            $packages = $payNowData->pluck('package')->unique('id');
            $itemsDetail = $payNowData;
            $payNowData = $payNowData->first()->append('total_amount')->toArray();
            $commonId = $payNowData['users']['cid'];
            $userIds = User::select('id')->where('cid', '=', $commonId)->get();
            $outputIds = array();
            foreach ($userIds as $output) {
                array_push($outputIds, $output->id);
            }
            $userSetting = AppSetting::whereIn('user_id', $outputIds)->get();
            $userSetting = $userSetting->first() ?: new AppSetting;
            return view('importexport.paymentDone', compact(
                'itemsDetail', 'payNowData', 'userSetting', 'packages'
            ));
        }
    }

    /* move item from payment pending to payment completed */



/* function to redirect users to paypal for payment */

    public function viewInvoice(\App\Logic\Payment\Stripe $stripe)
    {
        $ids = Input::get('token');
        $payNowId = Input::get('paynow');
        if (!empty($payNowId)) {
            $query = explode(',', base64_decode($payNowId));
            $byAppUser = 1;
        }
        if (!empty($ids)) {
            $query = explode(',', str_replace('PP', '', base64_decode($ids)));
            $byAppUser = 0;
        }

        $payNowData = Item::whereIn('id', $query)->where('status', '=', 5)->with('customers', 'users')->get();
        $mpayer=MasterChoiceItems::whereIn('item_id', $query)->with('users')->get();
        if($mpayer->first()->is_msc){
			$payer=User::find($mpayer->first()->user_id)->toArray();
		}else{
			$payer=User::find($mpayer->first()->cid)->toArray();
		}
        
        $itemsDetail = $payNowData;
        if ($payNowData->count() > 0) {
            $payNowData = $payNowData->first()->append('total_amount')->toArray();
            $commonId = $payNowData['users']['cid'];
            $userIds = User::select('id')->where('cid', '=', $commonId)->get();
            $outputIds = array();
            $adminUser = User::find($commonId);
            foreach ($userIds as $output) {
                array_push($outputIds, $output->id);
            }
            $userSetting = AppSetting::whereIn('user_id', $outputIds)->get();
            $userSetting = $userSetting->first() ?: new AppSetting;
            $processor = 'stripe';
            $stripe->setPayee($adminUser);
            $countries = \Config::get('settings.country');
            $subView = Input::get('subView');
            $compnay_name=Auth::user()->compnay_name;
            return view('importexport.viewNow', compact(
                            'itemsDetail', 'payNowData', 'adminUser', 'query', 'byAppUser', 'userSetting', 'processor', 'stripe', 'countries', 'subView', 'compnay_name', 'payer'
            ));
        } else {
            $payNowData = Item::whereIn('id', $query)->with('customers', 'users')->get();
            $itemsDetail = $payNowData;
            $payNowData = $payNowData->first()->append('total_amount')->toArray();
            $commonId = $payNowData['users']['cid'];
            $userIds = User::select('id')->where('cid', '=', $commonId)->get();
            $outputIds = array();
            foreach ($userIds as $output) {
                array_push($outputIds, $output->id);
            }
            $userSetting = AppSetting::whereIn('user_id', $outputIds)->get();
            $userSetting = $userSetting->first() ?: new AppSetting;
            return view('importexport.paymentDone', compact(
                            'itemsDetail', 'payNowData', 'userSetting'
            ));
        }
    }

    /* move item from payment pending to payment completed */

    public function processPayment()
    {
        $data = $_POST;
        if ($data['payment_status'] == 'Completed') {
            $ids = explode(',', $data['custom']);
            $amount = $data['payment_gross'];
            $paymentDate = date('Y-m-d H:i:s');
            foreach ($ids as $id) {
                DB::table('items')
                        ->where('id', $id)
                        ->update(array('status' => 6, 'date_paid' => $paymentDate, 'amount_paid' => $amount));
                $itemDetail = Item::where('id', '=', $id)->get();
                $logData = ['user_id' => $itemDetail->first()->user_id, 'item_id' => $id, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => 6, 'notes' => 'Auto move after payment'];
                ItemLog::create($logData);
            }
        }
    }

    /* function to get contact detials to customer for payment */

    public function contactLogs()
    {
        $data = Input::get('d');
        $view = Input::get('view');
        if (!empty($data)) {
            $ids = explode(',', base64_decode($data));
            $query = max($ids);
            $contactLog = CustomerItemLog::whereIn('item_id', $ids)->with('customers')->orderBy('contact_time', 'desc')->get();
            $html = View('importexport.contactLog', compact('contactLog', 'query', 'view'))->render();
            return response()->json(array('success' => true, 'html' => $html));
        }
    }

    /* save contact logs */

    public function saveContactLog(Request $request)
    {
        $validator = Validator::make($request->all(), [
                    'contact_medium' => 'required',
                    'contact_time' => 'required|date_format:"m/d/Y"',
                    'result' => 'required'
                        ], [
                    'contact_medium.required' => trans('Source is required'),
                    'contact_time.required' => trans('Date is required'),
                    'contact_time.date_format' => trans('Please enter valid date'),
                    'result.required' => trans('Result is required')
                        ]
        );
        if ($validator->fails()) {
            // If validation fails redirect back.
            return Response::json(array(
                        'fail' => true,
                        'errors' => $validator->getMessageBag()->toArray()
            ));
        }

        $data = $request->all();
        $customerItemLog = CustomerItemLog::create([
                    'customer_id' => $data['customer_id'],
                    'item_id' => $data['item_id'],
                    'contact_medium' => $data['contact_medium'],
                    'contact_time' => date('Y-m-d H:i:s', strtotime($data['contact_time'])),
                    'contact_details' => 'Contact Detail',
                    'result' => $data['result'],
                    'note' => $data['note']
        ]);
        $customerItemLog->save();
        return Response::json(array(
                    'success' => true,
                    'message' => 'Contact log saved successfully.'
        ));
    }

    /* payment received */

    public function paymentReceived(Request $request)
    {
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $admin = $request->user()->admin;

        $shipmentOrders = $admin->shipmentOrders()
            ->where('stage', Item::STATUS_PAYMENT_RECEIVED)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->with('items.logs', 'packages.items.customer', 'customerItemLogs')
            ->with('packages.items.locations', 'customer', 'user', 'itemLogs')
            ->get();

        $tz = $admin->settings->timezone;
        if (empty($tz)) {
            $ip = $this->get_client_ip();
            $url = 'http://freegeoip.net/json/' . $ip;
            $json = file_get_contents($url);
            $ipData = json_decode($json, true);
            if ($ipData['time_zone']) {
                $tz = $ipData['time_zone'];
            } else {
                $tz = "America/Chicago";
            }
        }
        return view('importexport.paymentReceived', compact('shipmentOrders', 'tz'));
    }

    /* save tracking detail */

    public function saveTrackingDetails(Request $request)
    {
        /* TODO: make validation work with multiple packages
        $validator = Validator::make($request->all(), [
                    'tracking_number' => 'required',
                        ], [
                    'tracking_number.required' => trans('Tracking number is required'),
                        ]
        );
        if ($validator->fails()) {
            return Response::json(array(
                        'fail' => true,
                        'errors' => $validator->getMessageBag()->toArray()
            ));
        }
         */
        $admin = $request->user()->admin;
        $data = $request->all();
        foreach ($request->input('packages') as $id => $packageInput) {
            $package = $admin->packages()->find($id);
            $info = $package->info ?: [];
            $info['tracking_code'] = $packageInput['tracking_code'];
            $package->info = $info;
            $package->save();
            $package->shipmentOrder->stage = $data['status'];
            $package->shipmentOrder->save();
        }
        $ids = array_filter(explode(',', $data['id']));
        $dataArray['ids'] = $ids;

        foreach ($ids as $id) {
            $item = Item::find($id);
            $item->notes = $data['notes'];
            $item->status = $data['status'];
            $item->save();

            $logData = ['user_id' => Auth::user()->id, 'item_id' => $id, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => $data['status'], 'notes' => $data['notes']];
            ItemLog::create($logData);
        }
        $this->notificationService->sendShipped($dataArray);
        return Response::json(array(
                    'success' => true,
                    'message' => 'Items updated successfully.'
        ));
    }

    /* update customer address */

    public function updateCustomerAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
                    'street' => 'required',
                    'city' => 'required',
                    'state' => 'required',
                    'zip' => 'required',
                    'country' => 'required',
                    'mobile_number' => 'required',
                    'email' => 'required',
                        ], [
                    'street.required' => trans('Street is required'),
                    'city.required' => trans('City is required'),
                    'state.required' => trans('State is required'),
                    'zip.required' => trans('Zip is required'),
                    'mobile_number.required' => trans('Mobile number is required'),
                    'email.required' => trans('Email is required'),
                        ]
        );
        if ($validator->fails()) {
            return Response::json(array(
                        'fail' => true,
                        'errors' => $validator->getMessageBag()->toArray()
            ));
        }
        $data = $request->all();
        $fromStage = $data['from_stage'] ?? Item::STATUS_PAYMENT_PENDING;
        $customerDetail = Customers::find($data['id']);
        $addressChanged = 0;

        if ($customerDetail->address2 != $data['address2'] || $customerDetail->street != $data['street'] || $customerDetail->city != $data['city'] || $customerDetail->country != $data['country'] || $customerDetail->state != $data['state'] || $customerDetail->zip != $data['zip']) {
            $addressChanged = 1;
        }

        DB::table('customers')
                ->where('id', $data['id'])
                ->update(array('email' => $data['email'],'mobile_number' => $data['mobile_number'],'last_name' => $data['last_name'],'first_name' => $data['first_name'],'street' => $data['street'], 'city' => $data['city'], 'country' => $data['country'], 'address2' => $data['address2'], 'state' => $data['state'], 'zip' => $data['zip']));

        if ($addressChanged && $fromStage == Item::STATUS_PAYMENT_PENDING) {
            $items = Item::where(['customer_id' => $data['id'], 'status' => 5])->get();
            if ($items->count() > 0) {
                foreach ($items as $item) {
                    DB::table('items')
                            ->where('id', $item->id)
                            ->update(array('address_changed' => 1, 'status' => 4, 'notes' => 'Address updated by customer'));

                    $logData = ['user_id' => $item->user_id, 'item_id' => $item->id, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => 4, 'notes' => 'Address updated by customer'];
                    ItemLog::create($logData);
                }
                $items[0]->shipmentOrder()->update(['stage' => Item::STATUS_PROCESSING]);
                Package::forItems($items)->removeShipmentInfo();
            }
        }

        if ($data['byAppUser']) {
            return response()->json(array('success' => true, 'address_changed' => $addressChanged, 'html' => 'Customer address has been updated. We need to recalculate the shipping cost. Customer will get updated shipping price soon through new email.'));
        } else {
            return response()->json(array('success' => true, 'address_changed' => $addressChanged, 'html' => 'Your address has been updated. We need to recalculate the shipping cost. You will get updated shipping price soon through new email.'));
        }
    }

    /* function to show shipped items list */

    public function shipped(Request $request)
    {
        $userId = $this->getAllUsersIds();
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';

        $shipmentOrders = $request->user()->admin->shipmentOrders()
            ->where('stage', Item::STATUS_SHIPPED)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->withUnpaidCustomerItems($userId)
            ->with('items._notes', 'itemLogs', 'packages', 'customerItemLogs')
            ->latest()
            ->get();

        return view('importexport.shipped', compact('shipmentOrders'));
    }

    /* function to show shipped items list */

    public function task()
    {
        $userId = $this->getAllUsersIds();
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $shippedItems = Item::where('status', '=', 5)
                        ->whereIn('user_id', $userId)
                        ->select(DB::raw('count(*) as lots'), DB::raw('MAX(notes) as notes'), DB::raw('SUM(bid_amount) as bid_amount'), DB::raw('GROUP_CONCAT(id SEPARATOR ",") as ids'), 'customer_id', DB::raw('MAX(date_paid) as date_paid'), DB::raw('MAX(id) as id'), DB::raw('MAX(total_packing_price) as total_packing_price'), DB::raw('MAX(shipping_price) as shipping_price'), DB::raw('MAX(tracking_number) as tracking_number'), DB::raw('MAX(carrier_used) as carrier_used'), DB::raw('MAX(shipping_option) as shipping_option'), DB::raw('MAX(shipping_price_discount+packing_price_discount+box_price_discount) as discount'))
                        ->groupBy('auction_date', 'customer_id')
                        ->with([
                            'customers' => function ($query) use ($search) {
                                $query->select('id', 'first_name', 'street', 'last_name', 'city', 'state', 'zip', 'email', 'mobile_number');
                                if(!empty($search)){
                                    $query->select('id', 'first_name','street', 'last_name', 'city', 'state','zip', 'email', 'mobile_number')->where(['id'=>$search]);
                                }else{
                                    $query->select('id', 'first_name','street', 'last_name', 'city', 'state','zip', 'email', 'mobile_number');
                                }   
                            },
                            'groups' => function ($query) {
                                $query->select('id', 'group');
                            },
                            'contact_logs' => function ($query) {
                                $query->select('contact_medium', 'contact_time')->orderBy('contact_time', 'desc');
                            },
                            'locations' => function ($query) {
                                $query->select('id', 'location');
                            },
                            'logs' => function ($query) {
                                $query->select('*')->where('step_moved', '=', 5)->where('notified', '=', 1)->orderBy('move_date', 'desc');
                            }])->get();
        return view('importexport.task', compact('shippedItems'));
    }

    /* function to show completed items list */

    public function completed(Request $request)
    {
        $userId = $this->getAllUsersIds();
        $search = !empty(app('request')->input('search'))?my_simple_crypt(app('request')->input('search'),'d'):'';
        $shipmentOrders = $request->user()->admin->shipmentOrders()
            ->where('stage', Item::STATUS_COMPLETED)
            ->where(empty($search) ? [] : ['customer_id' => $search])
            ->withUnpaidCustomerItems($userId)
            ->with('items.groups', 'items.locations', 'items._notes', 'packages')
            ->with(['items.logs' => function($q) { $q->where('step_moved', 9); }])
            ->latest()
            ->get();

        return view('importexport.completed', compact('shipmentOrders'));
    }

    /* paypal payment */

    public function paypalPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
                    'customer_name' => 'required',
                    'card_number' => 'required|ccn',
                    'card_code' => 'required|cvc',
                        ], [
                    'card_number.ccn' => 'Please enter valid credit card number',
                    'card_code.cvc' => 'Please enter valid cvv'
                        ]
        );
        if ($validator->fails()) {
            return Response::json(array(
                        'fail' => true,
                        'errors' => $validator->getMessageBag()->toArray()
            ));
        }
        $data = $request->all();
        $itemIds = $data['ids'];
        $itemId = max(explode(',', $itemIds));
        $items = Item::where('id', '=', $itemId)->with('customers')->get()->first();
        $amount = $items->total_packing_price + $items->shipping_price;
        $userSetting = AppSetting::where('user_id', '=', $data['admin_email'])->get()->first();
        $adminUser = User::find($data['admin_email']);

        $client = isset($userSetting->paypal_client) ? $userSetting->paypal_client : '';
        $secret = isset($userSetting->paypal_secret) ? $userSetting->paypal_secret : '';


        //$client="AZv5-G5N-_n__2Pd9Sgs5ckIOe2JGrz-Bj7ySCd_gq_9NHD8SuOuXycHMtW3crpI1ikOKrBwG_OSUrV6";
        //$secret="ECDAMvwb8nc6TuR8231Vwx-ihlbHxnygt0Nn8uNkFoRUE_SjPxfG3EAr8ZwqQmwiY4QLNL_viU-fE1I8";
        if (in_array($adminUser->email, ['ayush11ramola@gmail.com', 'pgibson78@gmail.com'])) {
            $paypalUrl = 'https://api.sandbox.paypal.com/v1/oauth2/token';
            $paymentUrl = 'https://api.sandbox.paypal.com/v1/payments/payment';
        } else {
            $paypalUrl = 'https://api.paypal.com/v1/oauth2/token';
            $paymentUrl = 'https://api.paypal.com/v1/payments/payment';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $paypalUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $client . ":" . $secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $result = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($result);

        if (isset($json->error)) {
            return Response::json(array(
                        'payment_fail' => true,
                        'data' => $json->error_description . '. Please contact administrator.'
            ));
        }

        // Now doing txn after getting the token 
        $userName = explode(' ', $data['customer_name']);
        $data = '{
          "intent":"sale",
          "redirect_urls":{
            "return_url":"http://<return URL here>",
            "cancel_url":"http://<cancel URL here>"
          },
          "payer": {
            "payment_method": "credit_card",
            "funding_instruments": [
              {
                "credit_card": {
                  "number": "' . $data['card_number'] . '",
                  "type": "' . $data['credit_card_type'] . '",
                  "expire_month": "' . $data['expiry_month'] . '",
                  "expire_year": "' . $data['expiry_year'] . '",
                  "cvv2": "' . $data['card_code'] . '",
                  "first_name": "' . @$userName[0] . '",
                  "last_name": "' . @$userName[1] . '"
                }
              }
            ]
          },
          "transactions":[
            {
              "amount":{
                "total":"' . number_format((float) $amount, 2, '.', '') . '",
                "currency":"USD"
              },
              "description":"Packing And Shipping Charges"
            }
          ]
        }
        ';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $paymentUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $json->access_token));

        $result = curl_exec($ch);
        curl_close($ch);
        if (empty($result)) {
            return Response::json(array(
                        'payment_fail' => true,
                        'data' => 'Payment is not done.Please try again or contact administrator.'
            ));
        }
        $paymentResponse = json_decode($result, true);
        if (isset($paymentResponse['state']) && $paymentResponse['state'] == "approved") {
            $ids = explode(',', $itemIds);
            $amount = $paymentResponse['transactions'][0]['amount']['total'];
            $paymentDate = date('Y-m-d');
            foreach ($ids as $id) {
                DB::table('items')
                        ->where('id', $id)
                        ->update(array('status' => 6, 'date_paid' => $paymentDate, 'amount_paid' => $amount));
                $itemDetail = Item::where('id', '=', $id)->get();
                $logData = ['user_id' => $itemDetail->first()->user_id, 'item_id' => $id, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => 6, 'notes' => 'Auto move after payment'];
                ItemLog::create($logData);
            }
            return Response::json(array(
                        'payment_success' => true,
                        'data' => ['tranaction_id' => $paymentResponse['id']]
            ));
        } else {
            return Response::json(array(
                        'payment_fail' => true,
                        'data' => $paymentResponse['details'][0]['issue']
            ));
        }
    }

    /**
     * Show items details.
     */
    public function getItemsData(Request $request)
    {
        if ($request->has('data')) {
            $admin = $request->user()->admin;
            $ids = explode(',', base64_decode($request->input('data')));
            $byAppUser = 0;
            $countries = \Config::get('settings.country');
            $stage = $request->input('stage');
            $userIds = $this->getAllUsersIds();

            $item = $admin->items()->whereIn('items.id', $ids)
                ->withUnpaidCustomerItems($userIds)
                ->with([
                    'item_images', 'users', 'box_logs.box', 'boxInventories', 'logs.user',
                    'logs' => function ($query) {
                        $query->select('*')->where('from_stage', '=',3)->orderBy('move_date', 'desc');
                    },
                ])->get();
            $packages = $admin->packages()->forItems($item)->withRelations()->get();
            $settings = $admin->settings;
            $unitsSystem = $settings->units_system;
            $lengthUnit = $unitsSystem === 'metric' ? 'mm' : 'in';
            $weightUnit = $unitsSystem === 'metric' ? 'grams' : 'ounces';
            $showShippingTab = in_array(
                $item->first()->status,
                [Item::STATUS_PAYMENT_PENDING, Item::STATUS_PROCESSING]
            );
            $html = View('importexport.discountForm', compact(
                'item', 'ids', 'stage', 'byAppUser', 'countries', 'packages',
                'lengthUnit', 'weightUnit', 'settings', 'user', 'showShippingTab'
            ))->render();

            return ['success' => true, 'html' => $html];
        }
    }

    /* fuction to move items to stages from edit pop */

    public function saveMoveStep(Request $request, \App\Logic\Shipping $shipping)
    {
        $data = $request->all();
        $validationRules = [
            'step' => 'required'
        ];
        if (array_key_exists('other_source', $data)) {
            $validationRules['other_source'] = 'required';
        }

        if (array_key_exists('payment_date', $data)) {
            $validationRules['payment_date'] = 'required';
        }

        $validator = Validator::make($request->all(), $validationRules
        );
        if ($validator->fails()) {
            return Response::json(array(
                        'fail' => true,
                        'errors' => $validator->getMessageBag()->toArray()
            ));
        }
        $stage = $data['stage'];
        $step = $data['step'];
        $ids = explode(',', $data['id']);
        $itemId = max($ids);
        $itemDetail = Item::with('package')->find($itemId);

        /* checking if moving from right stage. */
        if ($itemDetail->status != $stage) {
            return Response::json(array(
                        'stage_fail' => true,
                        'message' => 'Invalid request.'
            ));
        }

        /* check that items should not move from payment pending to payment received */
        if ($stage == 5 && $step == 6) {
            return Response::json(array(
                        'stage_fail' => true,
                        'message' => 'Cannot move without payment.'
            ));
        }
        /* cannot move items to next stage from ready to pack stage */
        if ($step == 4 && is_null($itemDetail->package)) {
            return Response::json(array(
                        'stage_fail' => true,
                        'message' => 'Cannot move to next without packing details.'
            ));
        }

        /* when moving items from processing step to payment pending */
        if ($step == 5 && $stage != 6 && (empty($itemDetail->shipping_price) || empty($itemDetail->carrier_used))) {
            return Response::json(array(
                        'stage_fail' => true,
                        'message' => 'Cannot move to next without shipping details.'
            ));
        }

        /* when moving from payment received to shipped */
        if ($step == 7 && (empty($itemDetail->tracking_number))) {
            return Response::json(array(
                        'stage_fail' => true,
                        'message' => 'Cannot move to next without tracking details.'
            ));
        }

        foreach ($ids as $id) {
            $itemData = Item::find($id);

            $moveStep = $step;
            if ($step == 'complaint_not_shipping') {
                $moveStep = 9;
                $itemData->notes = 'Complaint Not Shipping';
                $itemData->complaint = 1;
            }

            if ($step == 'never_paid') {
                $moveStep = 9;
                $itemData->notes = 'Never Paid';
            }

            if ($step == 'other_source') {
                $moveStep = 6;
                $itemData->payment_mode = $data['other_source'];
                $itemData->date_paid = date('Y-m-d', strtotime($data['payment_date']));
            }

            $itemData->status = $moveStep;
            /* remove data when moving back from shipped to payment received */
            if ($stage == 7 && $step == 6) {
                $itemData->tracking_number = '';
            }

            /* remove data when moving back from payment received to processing weigh */
            if ($stage == 5 && $step == 4) {
                $itemData->length = '0.00';
                $itemData->width = '0.00';
                $itemData->height = '0.00';
                $itemData->weight = '0.00';
                $itemData->shipping_price = '0.00';
                $itemData->insurance_price = '0.00';
                $itemData->carrier_used = '';
                $itemData->shipping_option = '';
                $itemData->total_price = '0.00';
            }

            /* remove data when moving back from payment received to processing weigh */
            if ($stage == 4 && $step == 3) {
                $itemData->length = '0.00';
                $itemData->width = '0.00';
                $itemData->height = '0.00';
                $itemData->weight = '0.00';
                $itemData->shipping_price = '0.00';
                $itemData->insurance_price = '0.00';
                $itemData->carrier_used = '';
                $itemData->shipping_option = '';
                $itemData->box_price = '0.00';
                $itemData->packing_price = '0.00';
                $itemData->total_packing_price = '0.00';
            }
            $itemData->save();
            $itemData->shipmentOrder->stage = $itemData->status;
            $itemData->shipmentOrder->save();

            $logData = ['user_id' => Auth::user()->id, 'item_id' => $id, 'move_date' => date('Y-m-d H:i:s'), 'step_moved' => $moveStep];
            if ($step == 'other_source') {
                $logData['notes'] = 'Paid By Other Source';
            }
            ItemLog::create($logData);
        }
        if ($step == 'other_source') {
            // Purchase shipment(s)
            $packages = Package::whereHas('items', function($query) use($ids) {
                $query->whereIn('id', $ids);
            })->get();
            foreach ($packages as $package) {
                $shipping->purchaseShipmentFor($package);
            }
        }

        return Response::json(array(
                    'success' => true,
                    'step' => $moveStep - 1,
                    'message' => 'Items moved to selected stage.'
        ));
    }

    function searchCustomer($id = null)
    {
        $type = Input::get('type');
        
        if(empty($type)){
            return back();
        }
        
        if($type=='shipping'){
            
            $customer = Item::select(DB::raw('count(*) as lots'), DB::raw('Max(created_at) as created_at'), DB::raw('GROUP_CONCAT(id SEPARATOR ",") as ids'), DB::raw('SUM(bid_amount) as bid_amount'), 'customer_id', 'status', DB::raw('MAX(id) as id'))
                    ->with('customers')
                    ->whereHas('customers', function($q) use ($id) {
                    $q->where('id', $id);
                    })
                    ->groupBy('customer_id','status')
                    ->get();
                    
            $search = '';
            $status = \Config::get('settings.processItemSteps');
            $statusuri = \Config::get('settings.processItemStepsURI');
            return view('importexport.customerSearch', compact('customer', 'search', 'status', 'statusuri','type'));
        }else{
            
            $item = AuctionItem::find(base64_decode($id));
            $search = '';
            $status = \Config::get('settings.autionSteps');
            $statusuri = \Config::get('settings.autionStepsUri');
            return view('importexport.customerSearch', compact('item', 'search', 'status', 'statusuri','type'));
        }
    }

    function exportModal()
    {

        $shippingSaintFields = $this->shippingfields;



        $html = View('importexport.exportModal', compact('shippingSaintFields'))->render();
        return response()->json(array('success' => true, 'html' => $html));
    }

    function updateNote(Request $request)
    {
        $data = $request->all();
        $ids = explode(',', $data['id']);

        $flag = DB::table('items')
                ->whereIn('id', $ids)
                ->update(array('notes' => $data['c']));

        echo $flag;
    }

    function updateAuctiondate(Request $request)
    {

        $data = $request->all();
        $ids = explode(',', $data['id']);
        $flag = DB::table('items')
                ->whereIn('id', $ids)
                ->update(array('auction_date' => $data['auction_date']));

        echo $flag;
    }

    function updateLocation(Request $request)
    {
        $data = $request->all();
        $flag = DB::table('items')
                ->whereIn('id', explode(',',base64_decode($data['id'])))
                ->update(array('location' => $data['location']));
        echo $flag;
    }

    function searchCustomerjson()
    {
        $data['q'] = Input::get('query');
        $type = Input::get('type');
        
        if($type=='shipping'){
            $customer = Item::select(DB::raw('count(*) as lots'), DB::raw('Max(created_at) as created_at'), DB::raw('GROUP_CONCAT(id SEPARATOR ",") as ids'), DB::raw('SUM(bid_amount) as bid_amount'), 'customer_id', DB::raw('MAX(status) as status'), DB::raw('MAX(id) as id'))->with('customers')->whereHas('customers', function($q) use ($data) {
                    $q->where('email', 'like', '%' . $data['q'] . '%')->orWhere('first_name', 'like', '%' . $data['q'] . '%')->orWhere('last_name', 'like', '%' . $data['q'] . '%')->orWhere('mobile_number', 'like', '%' . $data['q'] . '%');
                })->groupBy('customer_id')->get();
            $array = array();
            foreach ($customer as $row) {
                $array[] = array(
                    'label' => $row->customers->id,
                    'value' => $row->customers->first_name . ' ' . $row->customers->last_name . '<' . $row->customers->email . '>',
                );
            }
        }else{
            
            $items = AuctionItem::where('item', 'like', '%' . $data['q'] . '%')
                    ->orWhere('lot', 'like', '%' . $data['q'] . '%')
                    ->orWhere('description', 'like', '%' . $data['q'] . '%')
                    ->orWhereHas('customers', function( $query ) use ( $data ){
                        $query->where('mobile_number', 'like', '%' . $data['q'] . '%');
                    })
                    ->get();
                
            $array = array();
            foreach ($items as $row) {
                $lot = empty($row->lot)?0:$row->lot;
                $array[] = array(
                    'label' => base64_encode($row->id),
                    'value' =>  $lot. ' : ' . $row->item
                );
            }
        }

        return response()->json($array);
    }

    function newRecord()
    {
        $shippingSaintFields =$this->shippingSaintFieldsType;

        $shippingSaintFieldsim =$this->shippingSaintFieldsimType;

        $countries = \Config::get('settings.country');
        $html = View('importexport.newRecord', compact('shippingSaintFields', 'countries', 'shippingSaintFieldsim'))->render();
        return response()->json(array('success' => true, 'html' => $html));
    }

    public function addShippingData(Request $request)
    {
        $logData = $request->all();
        $userId = Auth::user()->id;
        $items = [];
        if(!empty($logData['lot'])){
            foreach ($logData['lot'] as $key=>$item){
                $insert = ['auction_date' => date('Y-m-d', strtotime($logData['auction_date'][$key])), 'customer_id' => $logData['datauserid'], 'user_id' => $userId, 'lot' => $item, 'description' => $logData['description'][$key], 'bid_amount' => $logData['bid_amount'][$key],'sender'=>$logData['sender']];
                $items[] = Item::create($insert);
            }
            ShipmentOrder::createOrdersForItems($items);
        }
        return redirect('/importExport')->with('success', 'Item(s) saved successfully');
    }

    function get_client_ip()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if (getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if (getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if (getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if (getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if (getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

}
