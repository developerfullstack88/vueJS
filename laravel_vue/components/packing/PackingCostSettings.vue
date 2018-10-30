<template>
<div>
    <div class="row">
        <label class="col-xs-3">Min Order Total</label>
        <label class="col-xs-3">Max Order Total</label>
        <label class="col-xs-5">Min Packing Cost</label>
    </div>
    <div v-for="(range, index) in ranges" class="row">
        <div class="col-xs-3">
            <div class="input-group">
                <div class="input-group-addon hidden-xs">$</div>
                <input type="text" class="form-control" v-model="range.min">
            </div>
        </div>
        <div class="col-xs-3">
            <div class="input-group">
                <div class="input-group-addon hidden-xs">$</div>
                <input type="text" class="form-control" v-model="range.max">
            </div>
        </div>
        <div class="col-xs-5">
            <div class="input-group">
                <div class="input-group-addon with-control">
                    <select v-model="range.cost_type">
                        <option>$</option>
                        <option>%</option>
                    </select>
                </div>
                <input type="text" class="form-control" v-model="range.cost_min">
            </div>
        </div>
        <div class="col-xs-1">
            <button type="button" class="btn btn-danger" @click="deleteRange(index)">
                <span class="glyphicon glyphicon-remove"></span>
            </button>
        </div>
    </div>
    <br>
    <div class="row">
        <div class="col-xs-3">
            <div class="input-group">
                <div class="input-group-addon hidden-xs">$</div>
                <input type="text" class="form-control" v-model="newRange.min">
            </div>
        </div>
        <div class="col-xs-3">
            <div class="input-group">
                <div class="input-group-addon hidden-xs">$</div>
                <input type="text" class="form-control" v-model="newRange.max">
            </div>
        </div>
        <div class="col-xs-5">
            <div class="input-group">
                <div class="input-group-addon with-control">
                    <select v-model="newRange.cost_type">
                        <option>$</option>
                        <option>%</option>
                    </select>
                </div>
                <input type="text" class="form-control" v-model="newRange.cost_min">
            </div>
        </div>
    </div>
    <br>
    <button type="button" class="btn btn-success" @click="addRange">
        <span class="glypicon glyphicon-plus"></span>
    </button>
    <input type="hidden" name="min_packing_charges" v-model="jsonRanges">
</div>
</template>

<script>
export default {
    props: ['settings'],
    data() {
        return {
            ranges: (this.settings || {}).min_packing_charges || [],
            newRange: { cost_type: '$' },
        };
    },
    methods: {
        addRange() {
            this.ranges.push({
                min: this.newRange.min,
                max: this.newRange.max,
                cost_min: this.newRange.cost_min,
                cost_type: this.newRange.cost_type,
            });
            this.newRange = { cost_type: this.newRange.cost_type };
        },
        deleteRange(index) {
            this.ranges.splice(index, 1);
        },
    },
    computed: {
        jsonRanges() {
            var ranges = this.ranges.slice(0);
            ranges.push(this.newRange)
            ranges = ranges.filter(range => {
                return range.min && range.max && range.cost_min && range.cost_type;
            });
            return JSON.stringify(ranges);
        },
    },
}
</script>
