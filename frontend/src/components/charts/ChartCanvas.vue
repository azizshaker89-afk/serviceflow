<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Chart, BarController, BarElement, CategoryScale, Filler, LineController, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js'
import { useChartTheme } from './useChartTheme'

Chart.register(BarController, BarElement, CategoryScale, Filler, LineController, LineElement, LinearScale, PointElement, Tooltip)

/**
 * Thin Chart.js wrapper: `build(theme)` returns a full config; the chart is rebuilt whenever
 * the data or the OS color scheme changes, and destroyed with the component.
 */
const props = defineProps({
  build: { type: Function, required: true },
  height: { type: Number, default: 240 },
  label: { type: String, required: true },
})

const canvas = ref(null)
const { version, theme } = useChartTheme()
let chart

function render() {
  chart?.destroy()
  if (canvas.value) chart = new Chart(canvas.value, props.build(theme()))
}

onMounted(render)
watch([() => props.build, version], render)
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
  <div class="chart-box" :style="{ height: `${height}px` }">
    <canvas ref="canvas" role="img" :aria-label="label"></canvas>
  </div>
</template>

<style scoped>
.chart-box { position: relative; width: 100%; }
</style>
