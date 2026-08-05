<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)

const PALETTE = ['#2E7D32', '#0288D1', '#B3261E', '#F9A825']

const props = defineProps({
  labels: { type: Array, required: true },
  // [{ label: String, data: Number[], color: String|undefined }]
  datasets: { type: Array, required: true },
  valueFormatter: { type: Function, default: (value) => value },
  height: { type: [String, Number], default: 280 },
})

const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.datasets.map((dataset, index) => {
    const color = dataset.color || PALETTE[index % PALETTE.length]

    return {
      label: dataset.label,
      data: dataset.data,
      borderColor: color,
      backgroundColor: `${color}26`,
      fill: props.datasets.length === 1,
      tension: 0.35,
    }
  }),
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: props.datasets.length > 1 },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.dataset.label}: ${props.valueFormatter(ctx.parsed.y)}`,
      },
    },
  },
}))

const style = computed(() => ({ height: typeof props.height === 'number' ? `${props.height}px` : props.height }))
</script>

<template>
  <div :style="style">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
