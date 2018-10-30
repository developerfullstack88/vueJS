<input type="hidden" name="packing_time" value="{{ $prepareShipData[0]->packing_time }}" id="packingTime">
<input type="hidden" name="packing_time_in_seconds" value="{{ $prepareShipData[0]->packing_time_in_seconds }}">
<input type="hidden" name="id" value="{{ $ids }}">

<div id="app">
<div class="modal-body" id="trayDetailForm">
    <div class="row">
        <div class="col-sm-6">
            <div class="row">
                <div class="col-lg-12">
                    <ul class="nav nav-tabs">
                        <li>
                            <a href="#1" data-toggle="tab">Images</a>
                        </li>
                        @if($tabs->count()>0)
                        @foreach($tabs as $tabIndex=>$tab)
                        <li class="{{ $tabIndex === 0 ? 'active' : '' }}">
                            <a href="#register-tab-{{$tabIndex}}" data-toggle="tab">{{$tab->tab_name}}</a>
                        </li>
                        @endforeach
                        @endif
                    </ul>
                    <div class="tab-content ">
                        <div class="tab-pane" id="1">
                            <div class="row">
                                <div class="col-lg-7">
                                    <h3></h3>
                                    <button type="button" class="btn btn-success" id="takePictures">Take Pictures
                                    </button>
                                    <div class="cam-container hidden">
                                        <div id="my_camera" style="margin-bottom: 5px;"></div>
                                        <a class="btn btn-success" onClick="take_snapshot()">Take Snapshot</a>
                                    </div>
                                </div>
                                <div class="col-lg-5 cam-container">
                                    <h3></h3>
                                    <div id="results" style="height: 300px; overflow-y: auto;">
                                        @foreach ($prepareShipData->pluck('item_images') as $images)
                                            @foreach ($images as $image)
                                                <div>
                                                    <img style="width:200px;margin-bottom:5px;"
                                                         src="{{asset('/img/package_images/'.$image->image)}}"/>
                                                    <a href="javascript:void(0)" class="removeImageDb"
                                                       data-id="{{base64_encode($image->id)}}">Delete</a>
                                                </div>
                                            @endforeach
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($tabs->count()>0)
                        @foreach($tabs as $tabIndex=>$tab)
                        @php $labelsData = []; @endphp
                        @foreach($tab->columns as $key => $column)
                        @php $labelsData[$column->position] = $key; @endphp             
                        @endforeach  
                        <div
                            class="tab-pane register-tab-body text-center {{ $tabIndex === 0 ? 'active' : '' }}"
                            id="register-tab-{{$tabIndex}}"
                        >
                            <div class="row" style="margin: 30px 0 0 0;">
                                @for($c=0;$c<=4;$c++)
                                @php $position = $c+1; @endphp
                                <div class="col-xs-2" style="padding-left:3px;padding-right: 3px;">
                                    @if(isset($labelsData[$position]))
                                    <a href="javascript:void(0)" class="column-label">{{$tab->columns[$labelsData[$position]]->label}}</a> 
                                    <i data-tab-id="{{$tab->id}}" data-column-id="{{$tab->columns[$labelsData[$position]]->id}}" data-position="{{$position}}" class="fa fa-edit columnLabelButton"></i>
                                    @php $buttonData = []; @endphp
                                    @foreach($tab->columns[$labelsData[$position]]->boxes as $key=>$box)
                                    @php $buttonData[$box->position] = $key; @endphp
                                    @endforeach
                                    @for($r=1;$r<=7;$r++)
                                    @if(isset($buttonData[$r]))
                                        @php $box = $tab->columns[$labelsData[$position]]->boxes[$buttonData[$r]]; @endphp
                                        <div class="clearfix"></div>
                                        <button type="button"
                                            id="register-button-{{ $box->id }}"
                                            data-category="{{ $box->category}}"
                                            data-measurement="{{$box->unit_measurement}}"
                                            data-unit="{{$box->unit_quantity}}"
                                            data-description="{{$box->label}}"
                                            data-cost="{{$box->retail_price}}"
                                            data-quantity="0"
                                            data-box-id="{{$box->id}}"
                                            data-stock="{{$box->stock}}"
                                            data-width="{{$box->width}}"
                                            data-height="{{$box->height}}"
                                            data-length="{{$box->length}}"
                                            class="btn btn-primary regsiterLabelButton register-button">
                                            {{$box->label}}
                                        </button>
                                    @else
                                    <div class="clearfix"></div>
                                    <button style="margin-top:15px;width:100%;white-space: unset; display: none">Button</button>
                                    @endif
                                    @endfor
                                    @else
                                    <a style="visibility:hidden" href="javascript:void(0)" class="column-label">Label</a> 
                                    <i style="visibility:hidden" class="fa fa-edit"></i>
                                    @endif
                                </div>
                                @endfor
                            </div>
                        </div>
                        @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            {!! csrf_field() !!}
            <div class="row timer-container">
                <h5 class="col-sm-3">
                    <b>{{ ($customer = $prepareShipData[0]->customers)->first_name }} {{ $customer->last_name }}</b>
                </h5>
                <h5 class="col-sm-3"><b>{{ $customer->city }}, {{ $customer->state }} {{ $customer->country }}</b></h5>
                <div :class="'col-sm-6 ' + clockClass">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <div class="pull-left">
                        @if(isset($appSetting->price) && $appSetting->price!=null)
                        <input id="toggle-event" data-toggle="toggle" type="checkbox">
                        @endif
                    </div>
                    <div class="pull-left">
                        <div class="Dprice">
                            <div class="innx">$0.0</div>
                            <input type="hidden" id="packing_time_price" name="packing_time_price">
                        </div>
                        <div class="clock flip-clock-sm"></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <ul v-if="errors.length" class="alert alert-danger">
                    <li v-for="error in errors">@{{ error }}</li>
                </ul>
                <packing-details
                    :initial-items="items"
                    :initial-packages="packages"
                    :packing-time="packingTime"
                    :packing-time-cost="packingTimeTotal"
                    :adjustment="adjustment"
                    :hourly-rate="hourlyRate"
                    :initial-shipping-price="shippingPrice"
                    :locations="locations"
                    :add-wrapping-id="addWrappingId"
                    :add-package-id="addPackageId"
                    :next-toggle="nextToggle"
                    :app-settings="appSettings"
                    @finished-packing="finishedPacking = true"
                    @not-finished-packing="finishedPacking = false"
                    @packing-tabs-active="packingTabsActive = true"
                    @packing-tabs-not-active="packingTabsActive = false"
                    @box-total-update="newTotal => boxTotal = newTotal"
                    @wrappings-total-update="newTotal => wrappingsTotal = newTotal"
                    @shipping-update="newPrice => shippingPrice = newPrice"
                    @active-tab-change="newTab => activeTab = newTab"
                    @insurance-update="cost => insurance = cost"
                ></packing-details>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <div class="col-sm-5 flex-row">
        @foreach ($pinnedBoxes as $box)
            <div>
                <button type="button"
                    id="register-button-{{ $box->id }}"
                    data-category="{{ $box->category}}"
                    data-measurement="{{$box->unit_measurement}}"
                    data-unit="{{$box->unit_quantity}}"
                    data-description="{{$box->label}}"
                    data-cost="{{$box->retail_price}}"
                    data-quantity="0"
                    data-box-id="{{$box->id}}"
                    data-stock="{{$box->stock}}"
                    data-width="{{$box->width}}"
                    data-height="{{$box->height}}"
                    data-length="{{$box->length}}"
                    class="btn btn-primary regsiterLabelButton register-button"
                >{{$box->label}}</button>
            </div>
        @endforeach
    </div>
    <div class="col-sm-6 scroll-x">
        <packing-costs
            :box-total="boxTotal"
            :wrappings-total="wrappingsTotal"
            :packing-time-total="packingTimeTotal"
            :shipping-price="shippingPrice"
            :insurance="insurance"
            :initial-adjustment="adjustment"
            :adjustment-editable="true"
            @adjustment-update="amount => adjustment = amount"
        ></packing-costs>
    </div>
    <div class="col-sm-1">
        <input type="submit" class="hide" id="hidden-submit" name="saveNext">

        <input v-if="activeTab === 'shipping'"
            type="button"
            value="Finished"
            class="btn btn-success"
            @click="validate"
        >
        <input v-else
            type="button"
            value="Next"
            @click="nextToggle = ! nextToggle"
            class="btn btn-success"
        >
    </div>
</div>
</div><!--app-->


<script language="JavaScript">

    var app = new Vue({
        el: '#app',
        data: {
            items: {!! $prepareShipData->toJson() !!},
            packages: {!! $packages->toJson() !!},
            packingTime: {{ (strtotime($prepareShipData[0]->packing_time) - strtotime('TODAY')) }},
            hourlyRate: {{ $hourlyRate }},
            shippingPrice: {{ $prepareShipData[0]->shipping_price }},
            insurance: 0,
            locations: {!! $locations->toJson() !!},
            appSettings: {!! $appSetting->toJson() !!},
            boxTotal: 0,
            wrappingsTotal: 0,
            addToWrappedId: 0,
            addWrappingId: 0,
            addPackageId: 0,
            finishedPacking: false,
            packingTabsActive: true,
            activeTab: 'item',
            nextToggle: false,
            clockClass: 'running',
            adjustment: {!! $packages->count() ? $packages->sum('adjustment') : 0 !!},
            isValid: true,
            errors: [],
        },
        mounted() {
            this.packingTime = this.packingTime > 0 ? this.packingTime : 0;
        },
        computed: {
            /**
             * Cost of packing time (hourly rate * packing time).
             */
            packingTimeTotal() {
                var packingTotal = this.hourlyRate * (this.packingTime / 3600);
                var orderTotal = this.items.reduce((total, item) => {
                    return total + item.bid_amount;
                }, 0);
                var minCostRanges = this.appSettings.min_packing_charges || [];
                var min, max, costMin, costType, minPackingTotal = 0;

                // check order total against min cost ranges from settings page
                minCostRanges.forEach(function(range) {
                    min = parseFloat(range.min);
                    max = parseFloat(range.max);
                    costMin = parseFloat(range.cost_min);

                    if (range.cost_type === '%') {
                        costMin = costMin / 100 * orderTotal;
                    }
                    if (orderTotal >= min && orderTotal <= max) {
                        packingTotal = Math.max(packingTotal, costMin);
                    }
                });
                // round to two decimals
                return Math.round(packingTotal * 100) / 100;
            },
        },
        watch: {
            finishedPacking() {
                this.checkClock();
            },
            packingTabsActive() {
                this.checkClock();
            },
        },
        methods: {
            /**
             * Start clock if not finishedPacking and packingTabsActive. Otherwise stop.
             */
            checkClock() {
                if ( ! window.clock) {
                    return;
                }
                if ( ! this.finishedPacking && this.packingTabsActive) {
                    window.clock.start();
                    this.clockClass = 'running';
                } else {
                    window.clock.stop();
                    this.clockClass = 'stopped';
                }
            },
            moveItemToStage: function(itemId, stage) {
                this.wrappedLoading = true;

                var component = this;
                // update item on server
                axios.put('/packing/item/' + itemId + '/stage/' + stage)
                    .then(function(response) {
                        component.wrappedLoading = false;
                        // update component's copy of item
                        component.updateItem(response.data.item);
                        if (response.data.thePackage) {
                            component.updatePackage(response.data.thePackage);
                        }
                    });
            },
            updateItem(newItem) {
                this.items.forEach((item, index) => {
                    if (item.id === newItem.id) {
                        Vue.set(this.items, index, newItem);
                    }
                });
            },
            validate() {
                // temporary disabling of validation
                return document.querySelector('#hidden-submit').click();
                this.isValid = true;
                this.errors = [];

                this.items.forEach(item => {
                    if (item.package_id === null) {
                        this.errors.push('Item not in package: ' + item.description);
                        this.isValid = false;
                    }
                });
                this.packages.forEach(thePackage => {
                    var isEmpty = this.items.filter(item => item.package_id === thePackage.id).length === 0;
                    isEmpty = this.packages.filter(p => p.parent_id === p.id).length === 0 || isEmpty;
                    if (thePackage.parent_id === null && isEmpty) {
                        this.errors.push('Package empty: ' + thePackage.name);
                        this.isValid = false;
                    }
                });
                this.packages.forEach(thePackage => {
                    if (thePackage.in_shipping === false) {
                        this.errors.push('Package not in shipping: ' + thePackage.name);
                        this.isValid = false;
                    }
                });
                if (this.isValid) {
                    // trigger the submit handler in pack-ready.js
                    document.querySelector('#hidden-submit').click();
                } else {
                    document.querySelector('#trayDetailForm').scrollTop = 0;
                }
            },
        },
    });

    /**
     * Add wrapping material to item.
     */
    $('body').on('click', '.regsiterLabelButton[data-category="Material"]', function () {
        if ($(this).data('stock') <= 0) {
            if (!confirm('Material is out of stock, do you still want to continue?')) {
                return false;
            }
        }
        app.addWrappingId = $(this).data('box-id');
        setTimeout(function() { app.addWrappingId = 0; }, 0);
    });

    /**
     * Create new package.
     */
    $('body').on('click', '.regsiterLabelButton[data-category="Box"]', function () {
        if ($(this).data('stock') <= 0) {
            if (!confirm('Box is out of stock, do you still want to continue?')) {
                return false;
            }
        }
        app.addPackageId = $(this).data('box-id');
        setTimeout(function() { app.addPackageId = 0; }, 0);
    });

    var hourlyPrice = {{ $hourlyRate }};
	
	//alert(hourlyPrice);
    /* click package pics */
    var picsCount = 1;
    function take_snapshot() {
        Webcam.snap(function (data_uri) {
            $('#results').prepend('<div class="pics-count-' + picsCount + '"><img style="width:200px;margin-bottom:5px;" src="' + data_uri + '"/><a href="javascript:void(0)" class="removeImage" data-id="' + picsCount + '">Delete</a>' +
                    '<input type="hidden" name="image[' + picsCount + ']" value="' + data_uri + '"></div>');
            picsCount = picsCount + 1;
        });
    }
    
    /*remove images and hidden inputs*/
    $(document).on('click', '.removeImage', function () {
        var id = $(this).data('id');
        $('.pics-count-' + id).remove();
    });
    
    /* remove image from db */
    $(document).on('click', '.removeImageDb', function () {
        var d = $(this).data('id');
        var th = $(this);
        if (d != '') {
            $.get("{{URL::to('deleteItemImages')}}", {'d': d}, function (response) {
                $(th).parent().remove();
                var unique_id = $.gritter.add({
                    title: 'Success!',
                    text: 'Image has been delete successfully.',
                    sticky: false,
                    time: 5000,
                    class_name: 'my-sticky-class'
                });
            });
        }
    });

    var clock;
    $(document).ready(function () {

        /* initialize webcam */
        $('#takePictures').click(function () {
            $(this).addClass('hidden');
            $('.cam-container').removeClass('hidden');
            Webcam.set({
                width: 320,
                height: 240,
                dest_width: 800,
                dest_height: 600,
                image_format: 'jpeg',
                jpeg_quality: 90
            });
            Webcam.attach('#my_camera');
        });

        /* init flip clock */
        var clockStarted = 0;
        clock = $('.clock').FlipClock({
            clockFace: 'HourlyCounter',
            autoStart: false,
            callbacks: {
                stop: function () {
                    if (clockStarted) {
                        $('.message').text('The clock has stopped!');
                    }
                    var time = this.factory.getTime().time;
                    $('#packingTime').val(time);
                },
                start: function () {
                    clockStarted = 1;
                    $('.message').text('The clock has started!');
                },
                interval: setHourlyFace,
            }
        });

        function setHourlyFace() {
            if (hourlyPrice != 0) {
                var xprice = (hourlyPrice * clock.getTime().time / (60 * 60));
                var FinalPrice = xprice.toFixed(1);
                $('.innx').text("$" + FinalPrice);
                $("#packing_time_price").val(FinalPrice)
                app.packingTime = clock.getTime().time;
            }
        }


        /* set time */
        clock.setTime(parseInt($('[name=packing_time_in_seconds]').val()));

        setHourlyFace();

        /* start timer */
        $(document).on('click', '#startTimer', function () {
            $(this).text('Resume');
            clock.start();
        });
        /* stop timer */
        $(document).on('click', '#stopTimer', function () {
            clock.stop();
        });

        /* clear form */
        $('#resetForm').click(function () {
            clock.setTime(0);
            clock.stop();
            $('.boxQuantity').each(function () {
                var quantity = parseInt($(this).text());
                var parentId = $(this).parent().parent().attr('id');
                parentId = parentId.split('-');
                var boxStock = parseInt($('#register-button-' + parentId[1]).data('stock'));
                boxStock = boxStock + quantity;
                $('#register-button-' + parentId[1]).data('quantity', 0);
                $('#register-button-' + parentId[1]).data('stock', boxStock);
            });
            
            var notToEmpty = ['id','savePending','saveNext','box_group','_token']
            $('#packingSave').find(':input').each(function () {
                if ($.inArray($(this).prop('name'), notToEmpty) == -1) {
                    $(this).val('');
                    if ($(this).prop('name') == 'items_packed[]') {
                        $(this).prop('checked',false);   
                    }
                    if($(this).prop('name') == 'box_used' || $(this).prop('name') == 'image'){
                        $(this).remove();
                    }
                }
            });
            $('.box-inputs').remove();
            $('#results').html('');
            $('#boxDetailHtml').html('');
            
            //remove details from DB
            $.post("{{url('reset-packing-detail')}}", $('#packingSave').serialize(), function (response) {
               $.gritter.add({
                    title: 'Success!',
                    text: response['message'],
                    sticky: false,
                    time: 5000,
                    class_name: 'my-sticky-class'
                }); 
            });
            
        });

        /* don't allow other than numbers and dot */
        $('.allow_numeric').keyup(function () {
            var value = $(this).val();
            var isValidMoney = /^\d{0,15}(\.\d{0,5})?$/.test(value);
            if (!isValidMoney) {
                $(this).val('');
            }
        });
        
        /* toggle items checkboxes */
        $(document).on('click','#selectAllItems',function(){
           if($(this).is(':checked')){
               $('.select-item-checkbox').prop('checked',true);
           }else{
               $('.select-item-checkbox').prop('checked',false);
           } 
        });
    });

    @if(isset($appSetting->price) && $appSetting->price!=null)
    $(function () {
        $('#toggle-event').bootstrapToggle({
                off: 'Dollar',
                on: 'Time',
                size: 'small',
        });

        $('#toggle-event').change(function () {
            if ($(this).prop('checked')) {
                $('.clock').show();
                $('.Dprice').hide();
            } else {
                $('.clock').hide();
                $('.Dprice').show();
            }
        })
    })
    @endif
	
	
function setUnit(vl){
        if($('#uintset').val()!=vl){
            if(vl=='oz'){
                if($('#weight').data('type')=='lbs'){
                var cnv=parseFloat($('#weight').val())*16;
                $('#weight').val(cnv.toFixed(2));
                $('#weight').data('type','oz');
              }
            else if($('#weight').data('type')=='lbsoz'){
                var daataArray=$('#weight').val().split(' ')
                var lbs=parseFloat(daataArray[0]);
                var oz=parseFloat(daataArray[2]);
               
                var cnv=(lbs*16)+oz
               
                $('#weight').val(cnv.toFixed(2));
                $('#weight').data('type','oz');
              }
            }else if(vl=='lbs'){
                if($('#weight').data('type')=='oz'){
                var cnv=parseFloat($('#weight').val())/16;
                $('#weight').val(cnv.toFixed(1));
                $('#weight').data('type','lbs');
             }
             else if($('#weight').data('type')=='lbsoz'){

                var daataArray=$('#weight').val().split(' ')
                var lbs=parseFloat(daataArray[0]);
                var oz=parseFloat(daataArray[2]);
                var cnv=lbs+(oz/16);
                cnv=parseFloat(cnv);
                $('#weight').val(cnv.toFixed(1));
                $('#weight').data('type','lbs');
              }
            }else if(vl=='lbsoz'){
                if($('#weight').data('type')=='lbs'){
                var lbs=parseFloat($('#weight').val());
                var dataArray=(lbs+ "").split('.');
                 var xval=parseFloat('.'+dataArray[1]);
                var xoz=(xval*16);
                if(isNaN(xoz)){
					  var cnv=dataArray[0]+' lbs 00 oz';
				}else{
					  var cnv=dataArray[0]+' lbs '+xoz.toFixed(1)+' oz';
				}
              
               // alert("cnv"+cnv)
                $('#weight').val(cnv);
                $('#weight').data('type','lbsoz');
            }else if($('#weight').data('type')=='oz'){
                var lbs=parseFloat($('#weight').val())/16;
               
                var dataArray=(lbs+ "").split('.');
            
             var xval=parseFloat('.'+dataArray[1]);
                var xoz=(xval*16);
               if(isNaN(xoz)){
                var cnv=dataArray[0]+' lbs 00 oz';
                }
                else{
					var cnv=dataArray[0]+' lbs '+xoz.toFixed(1)+' oz';
				}
                 
                $('#weight').val(cnv);
                $('#weight').data('type','lbsoz');
            }
            }
            $('#uintset').val(vl)
            $(".munit").html(vl+' <span class="caret"></span>');
        }
    }



function oztolbs(){
	
}

function lbstooz(){
	
}


$(function(){
    app.finishedPacking ? clock.stop() : clock.start();
})
</script>

<style>

    .toggle.btn {
        min-width: 59px;
        min-height: 34px;
        margin-left: 10px;
    }


    .innx {
        background: #333;
        padding: 0;
        width: 100px;
        font-size: 22px;
        color: #ccc;
        text-shadow: 0 1px 2px #000;
        text-align: center;
        border-radius: 15px;
        margin: 5px 12px;
    }



    .Dprice{
        display: {{ empty($appSetting->price) ? 'none' : 'block' }};
        width: 100px;
    }
    .flip-clock-wrapper {
        display: {{ empty($appSetting->price) ? 'block' : 'none' }};
        width: auto;
    }
    .register-button{
        margin-top: 15px;
        width: 100%;
        min-width: 70px;
        white-space: unset;
        padding:6px 1px;
        font-size: 13px;
        min-height: 70px;
    }
</style>
