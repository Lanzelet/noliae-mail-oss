<template>
  <svg viewBox="0 0 140 100" :class="['noliae-logo', { 'is-animating': animate }]"
       xmlns="http://www.w3.org/2000/svg" :aria-label="alt || 'Noliae Mail'" role="img">
    <rect class="bar bar-1" x="12"  y="35" width="10" height="30" rx="5" :fill="color"/>
    <rect class="bar bar-2" x="30"  y="25" width="10" height="50" rx="5" :fill="color"/>
    <rect class="bar bar-3" x="48"  y="15" width="10" height="70" rx="5" :fill="color"/>
    <rect class="bar bar-4" x="66"  y="22" width="10" height="56" rx="5" :fill="accent"/>
    <rect class="bar bar-5" x="84"  y="20" width="10" height="60" rx="5" :fill="color"/>
    <rect class="bar bar-6" x="102" y="30" width="10" height="40" rx="5" :fill="color"/>
    <rect class="bar bar-7" x="120" y="35" width="10" height="30" rx="5" :fill="color"/>
  </svg>
</template>

<script setup>
defineProps({
  color:   { type: String, default: 'currentColor' }, // couleur principale des barres
  accent:  { type: String, default: '#FF4D2E' },      // couleur de la barre centrale
  animate: { type: Boolean, default: true },          // active l'animation
  alt:     { type: String, default: '' },
});
</script>

<style scoped>
.noliae-logo .bar {
  transform-origin: center;
  transform-box: fill-box;
}
/* Animation type égaliseur audio : chaque barre scale Y en boucle avec un delay
   différent, donnant l'effet d'une onde sonore qui bouge. */
.noliae-logo.is-animating .bar {
  animation: noliae-equalize 1.6s ease-in-out infinite;
}
.noliae-logo.is-animating .bar-1 { animation-delay: 0.00s; }
.noliae-logo.is-animating .bar-2 { animation-delay: 0.10s; }
.noliae-logo.is-animating .bar-3 { animation-delay: 0.20s; }
.noliae-logo.is-animating .bar-4 { animation-delay: 0.30s; }
.noliae-logo.is-animating .bar-5 { animation-delay: 0.40s; }
.noliae-logo.is-animating .bar-6 { animation-delay: 0.50s; }
.noliae-logo.is-animating .bar-7 { animation-delay: 0.60s; }

@keyframes noliae-equalize {
  0%, 100% { transform: scaleY(1); }
  25%      { transform: scaleY(0.55); }
  50%      { transform: scaleY(1.25); }
  75%      { transform: scaleY(0.75); }
}

/* Respect du prefers-reduced-motion : on coupe l'anim, on garde le logo statique. */
@media (prefers-reduced-motion: reduce) {
  .noliae-logo.is-animating .bar { animation: none; }
}
</style>
