<script setup>
import { onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
  data: {
    type: Object,
    required: true,
    default: () => ({
      labels: [],
      values: []
    })
  }
});

const chartRef = ref(null);
let chart = null;

const createChart = () => {
  const ctx = chartRef.value.getContext('2d');
  
  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: props.data.labels,
      datasets: [{
        label: 'Sales',
        data: props.data.values,
        borderColor: '#4F46E5',
        backgroundColor: 'rgba(79, 70, 229, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          mode: 'index',
          intersect: false,
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                label += '₱' + new Intl.NumberFormat().format(context.parsed.y);
              }
              return label;
            }
          }
        }
      },
      scales: {
        x: {
          grid: {
            display: false
          },
          ticks: {
            maxRotation: 0,
            autoSkip: true,
            maxTicksLimit: 7
          }
        },
        y: {
          beginAtZero: true,
          grid: {
            borderDash: [2],
            drawBorder: false
          },
          ticks: {
            callback: function(value) {
              return '₱' + new Intl.NumberFormat().format(value);
            }
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index'
      }
    }
  });
};

const updateChart = () => {
  if (chart) {
    chart.data.labels = props.data.labels;
    chart.data.datasets[0].data = props.data.values;
    chart.update();
  }
};

watch(() => props.data, () => {
  updateChart();
}, { deep: true });

onMounted(() => {
  createChart();
});

defineExpose({
  updateChart
});
</script>

<template>
  <canvas ref="chartRef"></canvas>
</template> 