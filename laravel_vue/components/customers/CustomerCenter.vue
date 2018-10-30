<template>
     <div class="col-sm-12">
        <div class="panel panel-default">
            <header class="panel-heading">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h5><strong>Customer Center</strong></h5>
                </div>
            </header>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-1 pull-right" v-if="requestParam">
                        <a class="btn btn-success" :href="clearPath">Clear</a>
                    </div>
                    <div class="col-sm-4 pull-right">
                        <form class="form-horizontal" id="dateRangeForm" method="get">
                            <div class="form-group">
                                <input v-if="requestParam!=0" :value="requestParam" name="date_range" placeholder="Filter" type="text" id="date-range-pick" class="form-control" />
<input v-if="requestParam==0" value="" name="date_range" placeholder="Filter" type="text" id="date-range-pick" class="form-control" />
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row" style="margin-top: 15px;">
                    <div class="col-xs-12">
                        <section id="no-more-tables">
                            <table class=" table data-table table-bordered text-center table-hover table-condensed">
                                <thead class="cf">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Items</th>
                                        <th>Value</th>
                                        <th># of Packages</th>
                                        <th>Last Package Sent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="shippingCustomers.length" v-for="($customer, index) in shippingCustomers" class="showCustomerDetails"  data-type="shipping">
                                        <td>{{$customer.customer.first_name}} {{$customer.customer.last_name}}</td>
                                        <td>{{$customer.customer.email}}</td>
                                        <td>{{$customer.customer.mobile_number}}</td>
                                        <td>{{$customer.customer.items.length}}</td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tbody>
                                
                            </table>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['shippingCustomers','auctionCutomers','requestParam','clearPath'],
    data() {
        return {
            
        };
    }
}
</script>