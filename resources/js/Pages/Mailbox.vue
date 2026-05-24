<template>
  <div :class="['h-screen flex flex-col transition-colors', darkMode ? 'theme-dark' : '']"
       :style="{ background: darkMode ? '#0a0a0f' : '#f3f4f6' }">
    <!-- Barre du haut -->
    <header class="flex items-center justify-between px-3 sm:px-5 h-14 shrink-0" style="background:#15151a">
      <div class="flex items-center gap-2 sm:gap-2.5">
        <button @click="drawerOpen = !drawerOpen" class="lg:hidden text-gray-300 hover:text-white p-1 -ml-1"
                aria-label="Ouvrir les dossiers">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('hamburger')"/>
          </svg>
        </button>
        <svg viewBox="0 0 142 100" height="24">
          <rect x="12" y="35" width="10" height="30" rx="5" fill="#FAFAF7"/>
          <rect x="30" y="25" width="10" height="50" rx="5" fill="#FAFAF7"/>
          <rect x="48" y="15" width="10" height="70" rx="5" fill="#FAFAF7"/>
          <rect x="66" y="22" width="10" height="56" rx="5" fill="#FF4D2E"/>
          <rect x="84" y="20" width="10" height="60" rx="5" fill="#FAFAF7"/>
          <rect x="102" y="30" width="10" height="40" rx="5" fill="#FAFAF7"/>
          <rect x="120" y="35" width="10" height="30" rx="5" fill="#FAFAF7"/>
        </svg>
        <span class="text-white font-bold tracking-tight">Noliae Mail</span>
      </div>

      <!-- Recherche IMAP -->
      <form @submit.prevent="runSearch" class="hidden md:flex flex-1 max-w-xl mx-4 relative">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
          <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('search')"/>
        </svg>
        <input v-model="searchInput" type="text" placeholder="Rechercher dans les mails…"
          class="w-full bg-white/10 hover:bg-white/15 focus:bg-white text-white focus:text-gray-900 placeholder-gray-400
                 rounded-full pl-10 pr-9 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/40 transition"/>
        <button v-if="searchInput" type="button" @click="searchInput=''; runSearch();" title="Effacer"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-lg leading-none">&times;</button>
      </form>

      <div class="flex items-center gap-3 text-sm">
        <!-- Identity switcher : ma boîte + boîtes partagées auxquelles j'ai accès -->
        <div class="relative" v-click-outside="() => identityOpen = false">
          <button @click="identityOpen = !identityOpen"
                  class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-white/10 transition">
            <img class="w-7 h-7 rounded-full object-cover bg-gray-700"
                 :src="avatarUrl(currentIdentity.hash, currentIdentity.label)" alt=""/>
            <span class="hidden sm:inline text-gray-300 truncate max-w-[200px]">{{ currentIdentity.label }}</span>
            <svg v-if="(shared_mailboxes || []).length > 0" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-gray-400">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>
          <div v-if="identityOpen && (shared_mailboxes || []).length" class="absolute right-0 top-full mt-1 w-72 bg-white dark:bg-zinc-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-zinc-700 py-2 z-50">
            <div class="px-3 pb-2 text-[10px] uppercase font-bold tracking-wider text-gray-400">Mes identités</div>
            <a href="/webmail"
               :class="['flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-zinc-900 transition', !asSharedId && 'bg-[#FF4D2E]/5']">
              <img class="w-8 h-8 rounded-full object-cover bg-gray-300" :src="avatarUrl(me_hash, me_name || me)" alt=""/>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ me_name || 'Ma boîte' }}</div>
                <code class="text-[11px] text-gray-500 font-mono truncate block">{{ me }}</code>
              </div>
              <span v-if="!asSharedId" class="text-[10px] font-bold text-[#FF4D2E]">●</span>
            </a>
            <div class="px-3 pt-3 pb-2 text-[10px] uppercase font-bold tracking-wider text-gray-400">Boîtes partagées</div>
            <a v-for="s in shared_mailboxes" :key="s.id" :href="`/webmail?as=${s.id}`"
               :class="['flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-zinc-900 transition', asSharedId == s.id && 'bg-blue-50 dark:bg-blue-950']">
              <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-cyan-500 text-white flex items-center justify-center text-xs font-bold">
                {{ s.display_name.charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ s.display_name }}</div>
                <code class="text-[11px] text-gray-500 font-mono truncate block">{{ s.email }}</code>
              </div>
              <span class="text-[10px] uppercase text-blue-600 font-bold">{{ s.role }}</span>
            </a>
          </div>
        </div>
        <button @click="darkMode = !darkMode" :title="darkMode ? 'Mode clair' : 'Mode sombre'"
          class="w-8 h-8 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white flex items-center justify-center transition">
          <svg v-if="!darkMode" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('moon')"/>
          </svg>
          <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('sun')"/>
          </svg>
        </button>
        <button @click="togglePrivacy" :title="privacyMode ? 'Désactiver le mode confidentialité (Cmd/Ctrl+Shift+H)' : 'Mode confidentialité — flouter tout instantanément'"
          :class="['w-8 h-8 rounded-lg flex items-center justify-center transition',
                   privacyMode ? 'bg-rose-500/30 text-rose-200 hover:bg-rose-500/50' : 'text-gray-300 hover:bg-white/10 hover:text-white']">
          <svg v-if="!privacyMode" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('eye')"/>
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('dot')"/>
          </svg>
          <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('eyeOff')"/>
          </svg>
        </button>
        <button @click="shortcutsOpen = true" title="Raccourcis clavier (?)"
          class="w-8 h-8 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white flex items-center justify-center transition">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="6" width="18" height="12" rx="2"/>
            <path stroke-linecap="round" :d="iconPath('keyboard')"/>
          </svg>
        </button>
        <button @click="settingsOpen = true" title="Paramètres"
          class="w-8 h-8 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white flex items-center justify-center transition">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('gear')"/>
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('dot')"/>
          </svg>
        </button>
        <a href="/people" title="Annuaire de l'organisation"
           class="px-2 py-1.5 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white transition" aria-label="Annuaire">
          👥
        </a>
        <a href="/contacts" title="Carnet d'adresses"
           class="px-2 py-1.5 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white transition" aria-label="Contacts">
          📇
        </a>
        <a href="/account" title="Mon compte"
           class="px-3 py-1.5 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white transition">
          Compte
        </a>
        <button @click="logout" type="button"
                class="px-3 py-1.5 rounded-lg text-gray-300 hover:bg-white/10 hover:text-white transition">
          Déconnexion
        </button>
      </div>
    </header>

    <!-- Toast Undo Send -->
    <Transition name="toast">
      <div v-if="pendingSend" class="fixed bottom-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-noliae-ink text-white px-4 py-2.5 rounded-full shadow-2xl text-sm">
        <span>Message envoyé dans <span class="font-bold tabular-nums">{{ undoCountdown }}</span> s</span>
        <button @click="sendPendingNow" title="Envoyer immédiatement sans attendre la fenêtre d'annulation"
                class="px-3 py-1 rounded-full bg-white/15 text-white font-bold text-xs hover:bg-white/25 transition">
          Envoyer maintenant
        </button>
        <button @click="cancelPendingSend" class="px-3 py-1 rounded-full bg-[#FF4D2E] text-white font-bold text-xs hover:bg-[#df3c1f] transition">Annuler</button>
      </div>
    </Transition>

    <transition name="toast">
      <div v-if="toast"
           :class="['fixed bottom-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-5 py-3 rounded-full text-sm font-medium shadow-2xl',
                    toast.type === 'error' ? 'bg-rose-600 text-white' : 'bg-noliae-ink text-white']">
        <span>{{ toast.msg }}</span>
        <button v-if="toast.undo" @click="toast.undo()" class="px-3 py-1 rounded-full text-xs font-bold bg-[#FF4D2E] hover:bg-[#df3c1f] transition">Annuler</button>
      </div>
    </transition>

    <!-- Bandeau plein écran quand confidentialité active -->
    <Transition name="fade">
      <div v-if="privacyMode" class="fixed inset-x-0 top-14 z-40 bg-rose-600 text-white text-[11px] font-bold py-1.5 px-4 text-center shadow-md">
        🔒 Mode confidentialité actif — survol pour révéler · clique l'œil ou <kbd class="px-1 mx-0.5 rounded bg-white/20">Cmd/Ctrl+Shift+H</kbd> pour désactiver
      </div>
    </Transition>

    <div :class="['flex flex-1 min-h-0 relative', privacyMode ? 'privacy-on' : '']">
      <!-- Voile mobile pour fermer le tiroir -->
      <Transition name="fade">
        <div v-if="drawerOpen" @click="drawerOpen = false"
             class="lg:hidden fixed inset-0 top-14 bg-black/40 z-30"></div>
      </Transition>

      <!-- Dossiers -->
      <aside :class="['bg-white border-r border-gray-200 flex flex-col transform transition-transform',
                      'fixed lg:static inset-y-0 left-0 top-14 lg:top-0 z-40 w-72 lg:w-64 shrink-0',
                      drawerOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
        <div class="p-4 pb-2">
          <button @click="openCompose()"
            class="flex items-center gap-3 pl-4 pr-6 py-3.5 rounded-2xl
                   bg-[#FF4D2E] text-white text-sm font-bold shadow-lg shadow-[#FF4D2E]/20
                   hover:shadow-xl hover:shadow-[#FF4D2E]/30 hover:scale-[1.02] active:scale-95 transition">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('pencil')"/>
            </svg>
            Nouveau message
          </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-2 pb-3 space-y-0.5">
          <div v-for="f in dedupedFolders" :key="f.path" class="group relative">
            <button @click="openFolder(f.path)"
              :class="['w-full flex items-center gap-3 pl-4 pr-3 py-1.5 rounded-r-full text-sm transition',
                       f.path === folder ? 'bg-[#FF4D2E]/15 text-[#FF4D2E] font-bold' : 'text-gray-600 hover:bg-gray-100 font-medium']">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(meta(f.name).icon)"/>
              </svg>
              <span class="truncate flex-1 text-left">{{ meta(f.name).label }}</span>
              <svg v-if="f.locked" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.2" class="shrink-0 opacity-40 group-hover:opacity-60">
                <path stroke-linecap="round" stroke-linejoin="round"
                      :d="iconPath('lock')"/>
              </svg>
            </button>
            <button v-if="!f.locked" @click.stop="delFolder(f)" title="Supprimer le dossier"
              class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded leading-none
                     flex items-center justify-center text-gray-400 opacity-0 group-hover:opacity-100
                     hover:bg-rose-100 hover:text-rose-600 transition">×</button>
          </div>
          <button @click="newFolder"
            class="w-full flex items-center gap-3 px-3 py-2 mt-1 rounded-xl text-sm
                   text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition">
            <span class="w-[18px] text-center text-lg leading-none">+</span>
            <span>Nouveau dossier</span>
          </button>
        </nav>
        <div class="px-4 py-3 border-t border-gray-100">
          <p class="text-[11px] text-gray-400">Noliae Mail · messagerie souveraine</p>
        </div>
      </aside>

      <!-- Liste -->
      <section :class="['bg-white border-r border-gray-200 flex-col',
                        'w-full md:w-[22rem] lg:w-[25rem] shrink-0',
                        selected ? 'hidden md:flex' : 'flex']">
        <div class="h-12 flex items-center gap-2 px-3 border-b border-gray-100 shrink-0">
          <input type="checkbox" :checked="allSelected" @change="toggleAll"
                 class="w-4 h-4 rounded border-gray-300 text-[#FF4D2E] focus:ring-[#FF4D2E]/30 cursor-pointer"/>
          <template v-if="selectedUids.length">
            <span class="text-xs font-bold text-gray-700 tabular-nums">{{ selectedUids.length }} sélectionné{{ selectedUids.length > 1 ? 's' : '' }}</span>
            <div class="ml-auto flex items-center gap-0.5">
              <button @click="bulk('archive')" title="Archiver"
                class="w-7 h-7 rounded-md hover:bg-gray-100 text-gray-600 flex items-center justify-center transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('archive')"/>
                </svg>
              </button>
              <button @click="bulk('spam')" title="Spam"
                class="w-7 h-7 rounded-md hover:bg-amber-50 hover:text-amber-700 text-gray-600 flex items-center justify-center transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('spam')"/>
                </svg>
              </button>
              <button @click="bulk('unseen')" title="Marquer non lu"
                class="w-7 h-7 rounded-md hover:bg-gray-100 text-gray-600 flex items-center justify-center transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25"/>
                </svg>
              </button>
              <button @click="bulk('seen')" title="Marquer lu"
                class="w-7 h-7 rounded-md hover:bg-gray-100 text-gray-600 flex items-center justify-center transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('check')"/>
                </svg>
              </button>
              <div class="relative">
                <button @click="bulkMoveOpen = !bulkMoveOpen" title="Déplacer"
                  class="w-7 h-7 rounded-md hover:bg-gray-100 text-gray-600 flex items-center justify-center transition">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('list')"/>
                  </svg>
                </button>
                <div v-if="bulkMoveOpen"
                     class="absolute right-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-20 max-h-72 overflow-y-auto">
                  <button v-for="f in folders.filter(x => x.path !== folder)" :key="f.path"
                    @click="bulkMoveOpen = false; bulk('move', f.path)"
                    class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(meta(f.name).icon)"/>
                    </svg>
                    {{ meta(f.name).label }}
                  </button>
                </div>
              </div>
              <button @click="bulk('trash')" title="Supprimer"
                class="w-7 h-7 rounded-md hover:bg-rose-50 hover:text-rose-600 text-gray-600 flex items-center justify-center transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('trash')"/>
                </svg>
              </button>
              <button @click="clearSel" title="Annuler"
                class="w-7 h-7 rounded-md hover:bg-gray-100 text-gray-400 flex items-center justify-center text-base leading-none">×</button>
            </div>
          </template>
          <template v-else>
            <h2 class="text-sm font-black text-gray-900">{{ meta(currentFolderName).label }}</h2>
            <span class="ml-auto flex items-center gap-1.5 text-[11px] font-medium"
                  :class="polling ? 'text-emerald-600' : 'text-gray-400'">
              <span class="w-2 h-2 rounded-full"
                    :class="polling ? 'bg-emerald-500 animate-pulse' : 'bg-gray-300'"></span>
              En direct
            </span>
          </template>
        </div>
        <!-- Tabs catégories (INBOX) — style Gmail compact : icône + label actif -->
        <div v-if="tabsEnabled && /inbox/i.test(currentFolderName)"
             class="flex items-stretch border-b border-gray-200 bg-white shrink-0 min-w-0 overflow-hidden">
          <button v-for="tab in GMAIL_TABS" :key="tab.id" @click="inboxTab = tab.id"
            :title="tab.label"
            :class="['flex items-center justify-center gap-1.5 px-2 py-2.5 text-[11.5px] font-medium border-b-[3px] transition min-w-0',
                     inboxTab === tab.id
                       ? 'flex-[2] border-[#FF4D2E] text-gray-900 font-bold bg-[#FF4D2E]/[0.04]'
                       : 'flex-1 border-transparent text-gray-500 hover:bg-gray-50']">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="shrink-0"
                 :class="inboxTab === tab.id ? tab.colorClass : 'text-gray-400'">
              <path :d="tab.icon"/>
            </svg>
            <span v-if="inboxTab === tab.id" class="truncate">{{ tab.label }}</span>
            <span v-if="tab.id !== 'all' && tabCounts[tab.id] > 0 && inboxTab !== tab.id"
                  class="text-[10px] text-gray-400 tabular-nums font-bold">{{ tabCounts[tab.id] }}</span>
          </button>
          <button @click="tabsEnabled = false" title="Masquer les onglets"
            class="px-1.5 text-gray-300 hover:text-gray-700 text-base leading-none border-l border-gray-100">×</button>
        </div>

        <!-- Lien pour réafficher quand masqué -->
        <button v-if="!tabsEnabled && /inbox/i.test(currentFolderName)"
                @click="tabsEnabled = true; inboxTab = 'all'"
                class="text-[10.5px] text-gray-400 hover:text-[#FF4D2E] py-1.5 px-3 text-center border-b border-gray-100 shrink-0 transition">
          ⋯ Réafficher les onglets de tri
        </button>

        <div class="flex-1 overflow-y-auto">
          <div v-if="error" class="m-4 p-3 rounded-xl bg-rose-50 text-sm text-rose-700">{{ error }}</div>
          <div v-else-if="!messages.length" class="p-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-gray-100 to-gray-50 border border-gray-200 mb-3">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-gray-400"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('inbox')"/></svg>
            </div>
            <p class="text-sm font-bold text-gray-700">{{ search ? 'Aucun résultat' : 'Tout est traité' }}</p>
            <p class="text-[12px] text-gray-400 mt-1">{{ search ? 'Essaie d\'autres mots-clés.' : 'Plus rien dans ' + meta(currentFolderName).label + '.' }}</p>
          </div>
          <div v-for="m in displayedMessages" :key="m.uid"
            @contextmenu.prevent="openContext($event, m)"
            :class="['msg-row group/row flex items-start gap-2 px-3 py-2.5 border-b border-gray-50/80 transition relative cursor-pointer',
                     isSel(m.uid) ? 'bg-[#FF4D2E]/[0.08]'
                     : (selected && m.uid === selected.uid ? 'bg-[#FF4D2E]/[0.06] shadow-[inset_3px_0_0_#FF4D2E]'
                     : 'hover:bg-gray-50 hover:shadow-sm')]"
            @click="openMessage(m.uid)">
            <input type="checkbox" :checked="isSel(m.uid)" @click.stop @change="toggleSel(m.uid)"
                   class="mt-2.5 w-4 h-4 rounded border-gray-300 text-[#FF4D2E] focus:ring-[#FF4D2E]/30 shrink-0 cursor-pointer"/>
            <div class="relative shrink-0">
              <img class="w-9 h-9 rounded-full object-cover bg-gray-100"
                   :src="avatarOrLogo(m.from_hash, m.from || m.from_mail, m.from_mail)"
                   @error="onLogoError($event, m.from_mail)" alt=""/>
              <span v-if="isBrand(m.from_mail)" title="Marque vérifiée"
                    class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-sky-500 border-2 border-white flex items-center justify-center">
                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              </span>
            </div>
            <span class="min-w-0 flex-1">
              <span class="flex items-baseline justify-between gap-2">
                <span :class="['text-sm truncate', m.seen ? 'text-gray-700' : 'font-bold text-gray-900']">
                  {{ m.from || m.from_mail }}
                </span>
                <span class="text-[11px] text-gray-400 shrink-0 group-row-hover">{{ fmtDate(m.date) }}</span>
              </span>
              <span :class="['flex items-center gap-1.5 text-sm mt-0.5', m.seen ? 'text-gray-500' : 'font-semibold text-gray-800']">
                <svg v-if="m.has_att" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-gray-400">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('paperclip')"/>
                </svg>
                <span class="truncate">{{ m.subject }}</span>
                <span v-if="m.conv_count > 1" class="inline-block px-1.5 py-0 rounded-full bg-gray-200 text-gray-700 text-[10px] font-bold shrink-0">{{ m.conv_count }}</span>
              </span>
            </span>
            <!-- Étoile favori (clic = toggle) -->
            <button @click.stop="toggleStar(m)" :title="m.starred ? 'Retirer des favoris' : 'Ajouter aux favoris'"
              class="self-start mt-1 text-[15px] leading-none transition hover:scale-110"
              :class="m.starred ? 'text-amber-400' : 'text-gray-300 hover:text-amber-400'">
              {{ m.starred ? '★' : '☆' }}
            </button>
            <!-- Pastille couleur (clic = ouvre menu) -->
            <button v-if="m.label" @click.stop="cycleLabel(m)" :title="'Label : ' + m.label"
                    class="self-start mt-1.5 w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: LABEL_COLORS[m.label] }"></button>
            <span v-if="!m.seen" class="w-2 h-2 rounded-full bg-[#FF4D2E] shrink-0 mt-1.5"></span>

            <!-- Quick-actions au survol (style Superhuman) -->
            <div class="hidden group-hover/row:flex absolute right-2 top-1/2 -translate-y-1/2 items-center gap-0.5 bg-white/95 backdrop-blur-sm rounded-full px-1 py-1 shadow-lg border border-gray-100">
              <button @click.stop="quickArchive(m)" title="Archiver"
                class="w-7 h-7 rounded-full hover:bg-emerald-100 hover:text-emerald-700 text-gray-500 flex items-center justify-center transition">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('archive')"/></svg>
              </button>
              <button @click.stop="quickSeen(m)" :title="m.seen ? 'Marquer non lu' : 'Marquer lu'"
                class="w-7 h-7 rounded-full hover:bg-blue-100 hover:text-blue-700 text-gray-500 flex items-center justify-center transition">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="m.seen ? 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75' : 'm4.5 12.75 6 6 9-13.5'"/></svg>
              </button>
              <button @click.stop="quickTrash(m)" title="Supprimer"
                class="w-7 h-7 rounded-full hover:bg-rose-100 hover:text-rose-700 text-gray-500 flex items-center justify-center transition">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('trash')"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination : visible dès qu'on a une page pleine OU qu'on n'est pas page 1 -->
        <div v-if="messages.length >= per_page || page > 1"
             class="sticky bottom-0 z-10 flex items-center justify-between gap-2 px-3 py-2 bg-white/95 backdrop-blur border-t border-gray-200">
          <button @click="gotoPage(page - 1)" :disabled="page <= 1"
            class="px-3 py-1.5 rounded-lg text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center gap-1">
            <span>‹</span> Préc
          </button>
          <span class="text-xs text-gray-600 tabular-nums font-medium">
            <template v-if="total > 0">{{ pageRangeLabel }} <span class="text-gray-400">· page {{ page }}/{{ totalPages }}</span></template>
            <template v-else>Page {{ page }}</template>
          </span>
          <button @click="gotoPage(page + 1)" :disabled="total > 0 ? page >= totalPages : messages.length < per_page"
            class="px-3 py-1.5 rounded-lg text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center gap-1">
            Suiv <span>›</span>
          </button>
        </div>
      </section>

      <!-- Lecteur -->
      <main :class="['flex-1 min-w-0 bg-white overflow-y-auto',
                     selected ? 'block' : 'hidden md:block']">
        <div v-if="!selected" class="h-full flex items-center justify-center px-6">
          <div class="text-center max-w-sm">
            <div class="relative inline-block mb-5">
              <div class="absolute inset-0 bg-gradient-to-br from-[#FF4D2E]/20 via-orange-300/10 to-violet-300/20 blur-2xl rounded-full"></div>
              <svg class="relative" width="72" height="72" viewBox="0 0 24 24" fill="none">
                <defs>
                  <linearGradient id="env-grad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FF4D2E"/>
                    <stop offset="100%" stop-color="#f97316"/>
                  </linearGradient>
                </defs>
                <path :d="iconPath('envelope')"
                  stroke="url(#env-grad)" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h3 class="text-base font-black text-gray-700 mb-1">Aucun message sélectionné</h3>
            <p class="text-sm text-gray-400 leading-relaxed">Choisis un mail dans la liste à gauche, ou clique sur <span class="font-bold text-[#FF4D2E]">Nouveau message</span> pour écrire.</p>
            <div class="mt-6 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-[10.5px] font-bold text-emerald-700">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              Synchronisé en direct
            </div>
          </div>
        </div>
        <article v-else :key="selected.uid" class="reader-in max-w-5xl mx-auto px-4 sm:px-10 py-4 sm:py-8">
          <!-- Mobile back -->
          <button @click="backToList" class="md:hidden flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-[#FF4D2E] mb-3 -ml-1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('back')"/></svg>
            Retour
          </button>

          <!-- Mini toolbar icônes (Gmail-style en haut) -->
          <div class="hidden md:flex items-center gap-0.5 mb-5 -mt-1">
            <button @click="backToList" title="Retour à la liste" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500 hover:text-gray-900 flex items-center justify-center transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('back')"/></svg>
            </button>
            <span class="w-px h-5 bg-gray-200 mx-1"></span>
            <button @click="doArchive" title="Archiver (e)" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500 hover:text-emerald-600 flex items-center justify-center transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('archive')"/></svg>
            </button>
            <button @click="doSpam" title="Spam" class="w-9 h-9 rounded-full hover:bg-amber-50 text-gray-500 hover:text-amber-700 flex items-center justify-center transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('spam')"/></svg>
            </button>
            <button @click="doDelete" title="Supprimer (#)" class="w-9 h-9 rounded-full hover:bg-rose-50 text-gray-500 hover:text-rose-600 flex items-center justify-center transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('trash')"/></svg>
            </button>
            <span class="w-px h-5 bg-gray-200 mx-1"></span>
            <button @click="toggleSeen" :title="'Marquer non lu (u)'" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('envelope')"/></svg>
            </button>
            <button @click="snoozeOpen = !snoozeOpen" title="Snooze" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition relative">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('clock')"/></svg>
              <div v-if="snoozeOpen" @click.stop class="absolute right-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-20 text-left">
                <button v-for="o in snoozeOptions" :key="o.label" @click="snoozeNow(o.hours)"
                  class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex justify-between">
                  <span>{{ o.label }}</span><span class="text-gray-400 text-[11px]">{{ o.preview }}</span>
                </button>
              </div>
            </button>
            <div class="relative">
              <button @click="moveOpen = !moveOpen" title="Déplacer" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('list')"/></svg>
              </button>
              <div v-if="moveOpen" class="absolute left-0 top-full mt-1 w-56 bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-20 max-h-72 overflow-y-auto text-left">
                <button v-for="f in folders.filter(x => x.path !== folder)" :key="f.path"
                  @click="doMove(f.path)" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(meta(f.name).icon)"/></svg>
                  {{ meta(f.name).label }}
                </button>
              </div>
            </div>
            <button @click="cycleLabel(selected)" title="Libellé couleur" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('tag')"/></svg>
            </button>
            <span class="w-px h-5 bg-gray-200 mx-1"></span>
            <button @click="printMail" title="Imprimer" class="w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('print')"/></svg>
            </button>
          </div>

          <!-- Sujet + chip dossier -->
          <div class="flex items-start gap-3 mb-4">
            <h1 class="flex-1 text-2xl sm:text-[26px] font-normal text-gray-900 leading-[1.25]">{{ selected.subject }}</h1>
            <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded bg-gray-100 text-[10px] font-bold text-gray-600 uppercase tracking-wider shrink-0 mt-2">
              {{ meta(currentFolderName).label }}
            </span>
          </div>

          <!-- Badges sécurité -->
          <div v-if="securityBadges.length" class="flex flex-wrap gap-1.5 mt-3">
            <span v-for="b in securityBadges" :key="b.label"
                  :title="b.tooltip"
                  :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-bold border',
                           b.tone === 'green' ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                         : b.tone === 'amber' ? 'bg-amber-50 text-amber-700 border-amber-200'
                         : 'bg-rose-50 text-rose-700 border-rose-200']">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" v-if="b.icon==='lock'"><path :d="iconPath('lock')" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" v-else-if="b.icon==='check'"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('check')"/></svg>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" v-else-if="b.icon==='shield'"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('shieldCheck')"/></svg>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" v-else><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('warning')"/></svg>
              {{ b.label }}
            </span>
          </div>
          <!-- Bloc expéditeur compact + détails dépliables (style Gmail) -->
          <div class="flex items-start gap-3">
            <div class="relative shrink-0">
              <img class="w-10 h-10 rounded-full object-cover bg-gray-100"
                   :src="avatarOrLogo(selected.from_hash, selected.from || selected.from_mail, selected.from_mail)"
                   @error="onLogoError($event, selected.from_mail)" alt=""/>
              <span v-if="isBrand(selected.from_mail)" title="Marque vérifiée"
                    class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-sky-500 border-2 border-white flex items-center justify-center">
                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              </span>
            </div>
            <div class="min-w-0 flex-1 leading-tight">
              <div class="flex items-baseline gap-2 flex-wrap">
                <span class="text-sm font-bold text-gray-900">{{ selected.from || selected.from_mail }}</span>
                <span class="text-[12px] text-gray-500">&lt;{{ selected.from_mail }}&gt;</span>
              </div>
              <button @click="senderDetailsExpanded = !senderDetailsExpanded"
                class="text-[12px] text-gray-500 hover:text-gray-700 mt-0.5 flex items-center gap-1 transition">
                <span>à {{ isMeRecipient ? 'moi' : (selected.to || '—') }}</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                     :class="senderDetailsExpanded ? 'rotate-180' : ''" class="transition-transform"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('chevronDown')"/></svg>
              </button>
              <!-- Détails dépliables -->
              <div v-if="senderDetailsExpanded" class="mt-2 p-3 rounded-xl bg-gray-50 text-[12px] grid grid-cols-[80px_1fr] gap-x-3 gap-y-1">
                <span class="text-gray-400 font-bold text-right">De :</span>
                <span class="text-gray-700">{{ selected.from || selected.from_mail }} &lt;{{ selected.from_mail }}&gt;</span>
                <span class="text-gray-400 font-bold text-right">À :</span>
                <span class="text-gray-700 break-all">{{ selected.to }}</span>
                <span class="text-gray-400 font-bold text-right">Date :</span>
                <span class="text-gray-700">{{ fmtFull(selected.date) }}</span>
                <template v-if="selected.auth?.dkim">
                  <span class="text-gray-400 font-bold text-right">Signé :</span>
                  <span :class="selected.auth.dkim === 'pass' ? 'text-emerald-700' : 'text-amber-700'">
                    {{ selected.auth.dkim === 'pass' ? '✓ DKIM valide · ' + (selected.from_mail.split('@')[1] || '') : '⚠ DKIM ' + selected.auth.dkim }}
                  </span>
                </template>
                <span class="text-gray-400 font-bold text-right">Sécurité :</span>
                <span class="text-emerald-700">🔒 Chiffrement standard TLS</span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <span class="text-[11px] text-gray-500 tabular-nums whitespace-nowrap">{{ fmtFull(selected.date) }}</span>
              <button @click="toggleStar(selected); selected.starred = !selected.starred" :title="selected.starred ? 'Retirer des favoris' : 'Ajouter aux favoris'"
                class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-[16px] transition"
                :class="selected.starred ? 'text-amber-400' : 'text-gray-300 hover:text-amber-400'">
                {{ selected.starred ? '★' : '☆' }}
              </button>
              <button @click="reply" title="Répondre (r)" class="w-8 h-8 rounded-full hover:bg-gray-100 text-gray-500 hover:text-[#FF4D2E] flex items-center justify-center transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('reply')"/></svg>
              </button>
            </div>
          </div>
          <!-- ⚠️ Phishing warning -->
          <Transition name="panel-down">
            <div v-if="props.enable_noliae_ai && phishingRisk && phishingRisk !== 'low'" class="mt-3 p-3 rounded-xl border-2"
                 :class="phishingRisk === 'high' ? 'bg-rose-50 border-rose-300 text-rose-900' : 'bg-amber-50 border-amber-300 text-amber-900'">
              <div class="flex items-start gap-2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" class="shrink-0 mt-0.5"><path :d="iconPath('spam')"/></svg>
                <div>
                  <p class="font-black text-sm">{{ phishingRisk === 'high' ? '⚠ Phishing très probable' : 'Mail suspect' }}</p>
                  <p class="text-[12px] mt-0.5">{{ phishingReason }}</p>
                  <p class="text-[10.5px] mt-1 opacity-80">Analysé par IA Noliae. Ne clique aucun lien et ne télécharge aucune pièce jointe.</p>
                </div>
              </div>
            </div>
          </Transition>

          <!-- Mini-actions IA (visibles uniquement si IA Noliae activée) -->
          <div v-if="props.enable_noliae_ai && !isPgp" class="mt-3 flex flex-wrap gap-1.5">
            <button @click="aiTranslate" :disabled="aiBusy.translate"
              class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-violet-50 text-violet-700 border border-violet-200 hover:bg-violet-100 disabled:opacity-40">
              ✨ {{ aiBusy.translate ? '…' : (aiTranslated ? 'Original' : 'Traduire FR') }}
            </button>
            <button @click="aiSummarize" :disabled="aiBusy.summarize"
              class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-violet-50 text-violet-700 border border-violet-200 hover:bg-violet-100 disabled:opacity-40">
              ✨ {{ aiBusy.summarize ? '…' : (aiSummary ? 'Original' : 'Résumer') }}
            </button>
          </div>
          <div v-if="props.enable_noliae_ai && aiSummary" class="mt-2 p-3 rounded-xl bg-violet-50/50 border border-violet-200 text-sm whitespace-pre-line">{{ aiSummary }}</div>

          <!-- Smart Reply IA -->
          <div v-if="props.enable_noliae_ai && smartReplies.length && !isPgp" class="mt-3 flex flex-wrap gap-1.5">
            <button v-for="r in smartReplies" :key="r" @click="useSmartReply(r)"
              class="px-3 py-1.5 rounded-full text-[12px] font-bold border border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 transition">
              ✨ {{ r }}
            </button>
            <button v-if="smartLoading" class="px-3 py-1.5 rounded-full text-[12px] text-gray-400" disabled>
              <span class="animate-pulse">Génération…</span>
            </button>
          </div>

          <!-- Demande d'accusé de réception -->
          <div v-if="selected.receipt_to && selected.receipt_to !== me"
               class="mt-3 flex flex-wrap items-center gap-2 px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-200 text-[12px] text-emerald-800">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" d="M9 12.75 11.25 15 15 9.75"/>
            </svg>
            <span class="font-semibold">L'expéditeur demande un accusé de réception.</span>
            <button @click="sendReceipt" :disabled="receiptSent"
              class="ml-auto px-2.5 py-1 rounded-md bg-emerald-600 text-white text-[11px] font-bold hover:bg-emerald-700 disabled:opacity-40 transition">
              {{ receiptSent ? '✓ Envoyé' : 'Envoyer l\'accusé' }}
            </button>
          </div>

          <!-- Bouton brouillon (uniquement dans Brouillons) -->
          <div v-if="isDraftFolder" class="mt-4">
            <button @click="editDraft"
              class="px-2.5 py-1.5 rounded-md bg-violet-600 text-white text-[11px] font-bold hover:bg-violet-700 transition flex items-center gap-1.5">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('pencil')"/>
              </svg>
              Modifier le brouillon
            </button>
          </div>
          <div v-if="selected.attachments && selected.attachments.length" class="mt-4 space-y-3">
            <div class="text-[11px] uppercase tracking-wider font-bold text-gray-500">
              {{ selected.attachments.length }} pièce{{ selected.attachments.length > 1 ? 's' : '' }} jointe{{ selected.attachments.length > 1 ? 's' : '' }}
              <a v-if="selected.attachments.length > 1" :href="`/webmail/attachments-zip?folder=${encodeURIComponent(folder)}&uid=${selected.uid}`"
                 class="ml-2 text-[#FF4D2E] hover:underline normal-case">Tout télécharger</a>
            </div>
            <!-- Cards Outlook-style : icône type fichier + nom + taille + actions -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
              <div v-for="a in selected.attachments" :key="a.name"
                   class="group flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-[#FF4D2E]/40 hover:shadow-sm transition">
                <div :class="['shrink-0 w-10 h-10 rounded-lg flex items-center justify-center font-bold text-white text-[11px] tracking-tight',
                              attColor(a.name)]">
                  {{ attBadge(a.name) }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-semibold truncate text-gray-900 dark:text-gray-100" :title="a.name">{{ a.name }}</div>
                  <div class="text-[11px] text-gray-600 dark:text-gray-400">{{ humanSize(a.size) }}</div>
                </div>
                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                  <a v-if="attCanPreview(a.name)" :href="attachmentUrl(a.name, true)" target="_blank"
                     title="Aperçu" class="w-7 h-7 rounded hover:bg-gray-100 dark:hover:bg-zinc-700 flex items-center justify-center text-gray-600 dark:text-gray-300">👁</a>
                  <a :href="attachmentUrl(a.name, false)" :download="a.name"
                     title="Télécharger" class="w-7 h-7 rounded hover:bg-gray-100 dark:hover:bg-zinc-700 flex items-center justify-center text-gray-600 dark:text-gray-300">⬇</a>
                </div>
              </div>
            </div>
            <!-- Aperçu inline pour les images -->
            <div v-for="a in selected.attachments.filter(x => /\.(png|jpe?g|gif|webp|svg)$/i.test(x.name))"
                 :key="'img-'+a.name" class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50 max-w-lg">
              <div class="px-3 py-1.5 text-[11px] text-gray-500 font-bold bg-white border-b border-gray-100 flex justify-between">
                <span>🖼 {{ a.name }}</span>
                <a :href="attachmentUrl(a.name, false)" :download="a.name" class="text-[#FF4D2E] hover:underline">⬇ Télécharger</a>
              </div>
              <img :src="attachmentUrl(a.name, true)" :alt="a.name" class="w-full" style="max-height:60vh;object-fit:contain;background:#0a0a0a"/>
            </div>

            <!-- Preview PDF -->
            <div v-for="a in selected.attachments.filter(x => /\.pdf$/i.test(x.name))"
                 :key="'pdf-'+a.name" class="rounded-xl overflow-hidden border border-gray-200 max-w-2xl">
              <div class="px-3 py-1.5 text-[11px] text-gray-500 font-bold bg-white border-b border-gray-100 flex justify-between">
                <span>📄 {{ a.name }}</span>
                <a :href="attachmentUrl(a.name, false)" :download="a.name" class="text-[#FF4D2E] hover:underline">⬇ Télécharger</a>
              </div>
              <embed :src="attachmentUrl(a.name, true)" type="application/pdf" class="w-full" style="height:70vh"/>
            </div>
          </div>
          <!-- Fil de conversation : autres messages du même thread -->
          <div v-if="selected.thread && selected.thread.length > 1" class="mt-5 border border-gray-100 rounded-2xl overflow-hidden">
            <div class="px-3 py-2 bg-gray-50 text-[11px] font-bold text-gray-500 uppercase tracking-widest">
              💬 Conversation · {{ selected.thread.length }} messages
            </div>
            <div v-for="t in selected.thread" :key="t.uid"
                 :class="['flex items-center gap-2 px-3 py-2 border-t border-gray-50 hover:bg-gray-50 cursor-pointer transition',
                          t.uid === selected.uid ? 'bg-[#FF4D2E]/[0.06]' : '']"
                 @click="t.uid !== selected.uid && openMessage(t.uid)">
              <img class="w-7 h-7 rounded-full object-cover bg-gray-100"
                   :src="avatarUrl(t.from_hash, t.from)" alt=""/>
              <span class="flex-1 truncate text-sm" :class="t.seen ? 'text-gray-600' : 'font-bold text-gray-900'">
                {{ t.from }}
                <span v-if="t.uid === selected.uid" class="ml-1.5 text-[10px] font-bold text-[#FF4D2E]">• AFFICHÉ</span>
              </span>
              <span class="text-[11px] text-gray-400 shrink-0">{{ fmtDate(t.date) }}</span>
            </div>
          </div>

          <hr class="my-5 border-gray-100">

          <!-- Message chiffré PGP : panneau de déchiffrement -->
          <div v-if="isPgp && !pgpDecrypted" class="p-4 rounded-2xl border border-violet-200 bg-violet-50">
            <div class="flex items-center gap-2 mb-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="text-violet-700"><path :d="iconPath('lock')" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span class="text-sm font-bold text-violet-800">Message chiffré PGP</span>
            </div>
            <p class="text-[12px] text-violet-900 mb-2">Ce message a été chiffré pour ta clé. Entre ta passphrase pour le déchiffrer.</p>
            <div class="flex flex-wrap gap-2">
              <input v-model="pgpDecryptPass" type="password" placeholder="Passphrase de la clé privée"
                @keydown.enter="decryptCurrent"
                class="flex-1 min-w-[200px] text-sm rounded-lg border border-violet-300 bg-white px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-violet-400/40"/>
              <button @click="decryptCurrent" :disabled="pgpDecryptBusy || !pgpDecryptPass"
                class="px-4 py-1.5 rounded-full text-[12px] font-bold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-40">
                {{ pgpDecryptBusy ? 'Déchiffrement…' : '🔓 Déchiffrer' }}
              </button>
            </div>
            <p v-if="pgpDecryptError" class="mt-2 text-[11px] text-rose-600 font-medium">{{ pgpDecryptError }}</p>
          </div>
          <pre v-else-if="isPgp && pgpDecrypted"
               class="whitespace-pre-wrap text-sm text-gray-800 leading-relaxed p-3 rounded-xl bg-emerald-50 border border-emerald-200"
               style="font-family:inherit">{{ pgpDecrypted }}</pre>
          <pre v-else-if="aiTranslated"
               class="whitespace-pre-wrap text-sm text-gray-800 leading-relaxed p-3 rounded-xl bg-violet-50/30 border border-violet-200"
               style="font-family:inherit">{{ aiTranslated }}</pre>
          <iframe v-else-if="selected.html" :srcdoc="selected.html" sandbox="allow-same-origin allow-popups"
                  @load="autosizeIframe"
                  class="w-full bg-white rounded-lg" style="border:0;height:120px;transition:height .2s ease"></iframe>
          <pre v-else class="whitespace-pre-wrap text-sm text-gray-800 leading-relaxed" style="font-family:inherit">{{ selected.text || '(message vide)' }}</pre>

          <!-- Composeur inline (quick reply façon Gmail) -->
          <Transition name="panel-down">
            <div v-if="inlineReplyOpen" class="mt-5 flex gap-3 items-start">
              <img :src="avatarUrl(me_hash, me_name || me)" alt=""
                class="w-9 h-9 rounded-full object-cover bg-gray-100 shrink-0"/>
              <div class="flex-1 min-w-0 border border-gray-200 rounded-2xl bg-white shadow-sm overflow-hidden">
                <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-50">
                  <button @click="inlineReplyType = inlineReplyType === 'reply' ? 'forward' : 'reply'"
                          :title="inlineReplyType === 'reply' ? 'Répondre' : 'Transférer'"
                          class="w-7 h-7 rounded-full hover:bg-gray-100 text-gray-500 flex items-center justify-center transition">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(inlineReplyType === 'reply' ? 'reply' : 'forward')"/></svg>
                  </button>
                  <span class="text-[13px] text-gray-700 flex-1 truncate">
                    <span class="font-bold">{{ selected.from || selected.from_mail }}</span>
                    <span class="text-gray-400">({{ selected.from_mail }})</span>
                  </span>
                  <button @click="expandInlineToModal" title="Ouvrir en plein écran"
                          class="w-7 h-7 rounded-full hover:bg-gray-100 text-gray-400 flex items-center justify-center transition">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('maximize')"/></svg>
                  </button>
                </div>
                <div ref="inlineEditorRef" contenteditable="true"
                     @input="composeDirty = true"
                     @paste="onEditorPaste" @drop.prevent="onEditorDrop" @dragover.prevent
                     @mouseup="cacheSelection" @keyup="cacheSelection" @blur="cacheSelection"
                     class="px-4 py-3 text-sm leading-relaxed min-h-[100px] max-h-[40vh] overflow-y-auto focus:outline-none"></div>
                <div class="flex items-center gap-1 px-2 py-1.5 border-t border-gray-50 bg-gray-50/50">
                  <button @click="sendInlineReply" :disabled="sending"
                          class="flex items-center gap-1.5 pl-4 pr-3 py-1.5 rounded-full bg-[#FF4D2E] text-white text-sm font-bold hover:bg-[#df3c1f] disabled:opacity-40 shadow-sm transition">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('send')"/></svg>
                    {{ sending ? 'Envoi…' : 'Envoyer' }}
                  </button>
                  <span class="w-px h-5 bg-gray-200 mx-1"></span>
                  <button @click="inlineFormat('bold')" title="Gras" class="w-7 h-7 rounded hover:bg-gray-200 text-gray-600 flex items-center justify-center text-sm font-bold">B</button>
                  <button @click="inlineFormat('italic')" title="Italique" class="w-7 h-7 rounded hover:bg-gray-200 text-gray-600 flex items-center justify-center text-sm" style="font-family:serif">I</button>
                  <button @click="inlineFormat('underline')" title="Souligné" class="w-7 h-7 rounded hover:bg-gray-200 text-gray-600 flex items-center justify-center text-sm"><u>U</u></button>
                  <button @click="$refs.inlineFileInput.click()" title="Joindre un fichier" class="w-7 h-7 rounded hover:bg-gray-200 text-gray-500 flex items-center justify-center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('paperclip')"/></svg>
                  </button>
                  <input ref="inlineFileInput" type="file" multiple class="hidden" @change="addFiles"/>
                  <button @click="inlineMakeLink" title="Insérer un lien" class="w-7 h-7 rounded hover:bg-gray-200 text-gray-500 flex items-center justify-center">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('link')"/></svg>
                  </button>
                  <span class="ml-auto text-[11px] text-gray-400 font-bold" v-if="attachedFiles.length">{{ attachedFiles.length }} PJ</span>
                  <button @click="closeInlineReply" title="Supprimer le brouillon"
                          class="w-7 h-7 rounded hover:bg-rose-100 text-gray-400 hover:text-rose-600 flex items-center justify-center transition">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('trash')"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </Transition>

          <!-- Boutons Répondre / Transférer en gros, en bas (style Gmail) -->
          <div v-if="!inlineReplyOpen" class="mt-6 flex flex-wrap gap-2.5 pt-4 border-t border-gray-100">
            <button @click="reply"
              class="flex items-center gap-2 px-5 py-2.5 rounded-full border-2 border-gray-200 hover:border-[#FF4D2E] hover:bg-[#FF4D2E]/[0.04] text-gray-700 hover:text-[#FF4D2E] font-bold text-sm transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('reply')"/></svg>
              Répondre
            </button>
            <button @click="forward"
              class="flex items-center gap-2 px-5 py-2.5 rounded-full border-2 border-gray-200 hover:border-gray-400 hover:bg-gray-50 text-gray-700 font-bold text-sm transition">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('forward')"/></svg>
              Transférer
            </button>
            <button @click="senderInfoOpen = true" title="Détails techniques"
              class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-full text-gray-500 hover:bg-gray-100 text-xs font-bold transition">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('info')"/></svg>
              Détails
            </button>
            <button @click="showRaw" title="Afficher l'original (.eml)"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-gray-500 hover:bg-gray-100 text-xs font-bold transition">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('code')"/></svg>
              Original
            </button>
            <button @click="printMail" title="Imprimer"
              class="flex items-center justify-center w-8 h-8 rounded-full text-gray-500 hover:bg-gray-100 transition">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('print')"/></svg>
            </button>
          </div>
        </article>
      </main>
    </div>

    <!-- Composeur -->
    <Transition name="modal">
    <div v-if="composing" class="fixed inset-0 z-40 bg-black/50 flex items-end sm:items-center justify-center sm:p-4">
      <div :class="['compose-panel bg-white w-full shadow-2xl flex flex-col overflow-hidden transition-all duration-200',
                    composeMax
                      ? 'sm:max-w-6xl sm:max-h-[96vh] sm:h-[96vh] sm:rounded-2xl'
                      : 'sm:max-w-2xl sm:max-h-[94vh] sm:rounded-2xl']">
        <div class="flex items-center justify-between px-4 sm:px-5 py-3" style="background:#15151a">
          <h2 class="font-bold text-white text-sm">Nouveau message</h2>
          <div class="flex items-center gap-1">
            <button @click="composeMax = !composeMax" :title="composeMax ? 'Réduire' : 'Agrandir'"
              class="w-8 h-8 rounded-md text-gray-400 hover:text-white hover:bg-white/10 flex items-center justify-center transition">
              <svg v-if="!composeMax" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('maximize')"/>
              </svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('minimize')"/>
              </svg>
            </button>
            <button @click="composing = false" title="Fermer"
              class="w-8 h-8 rounded-md text-gray-400 hover:text-white hover:bg-white/10 text-2xl leading-none flex items-center justify-center">&times;</button>
          </div>
        </div>

        <!-- Barre IA Noliae (visible uniquement si activée par l'admin dans Paramètres) -->
        <div v-if="props.enable_noliae_ai" class="px-4 sm:px-5 pt-3 pb-1">
          <div v-if="!aiPanelOpen" class="flex items-center justify-between gap-3">
            <button @click="openAi" type="button"
              class="ai-btn group flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold
                     bg-gradient-to-r from-violet-500 via-fuchsia-500 to-[#FF4D2E] text-white
                     shadow-sm hover:shadow-md hover:scale-[1.02] transition">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                <path :d="iconPath('sparkle')"/>
              </svg>
              Rédiger avec l'IA Noliae
            </button>
            <span class="text-[10px] text-gray-400 hidden sm:inline">Décris ton intention, l'IA rédige le mail.</span>
          </div>
          <Transition name="panel-down" appear>
          <div v-if="aiPanelOpen" class="rounded-xl border border-violet-200 bg-violet-50/50 p-2.5">
            <div class="flex items-center justify-between mb-1.5">
              <span class="flex items-center gap-1.5 text-[11px] font-bold text-violet-700">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path :d="iconPath('sparkle')"/></svg>
                IA Noliae
              </span>
              <button @click="aiPanelOpen = false" type="button"
                class="text-gray-400 hover:text-gray-600 text-base leading-none">&times;</button>
            </div>
            <textarea v-model="aiPrompt" rows="2" :disabled="aiLoading"
              placeholder="Ex : Réponds que je suis dispo jeudi 14h pour la réunion, ton chaleureux."
              class="w-full text-sm px-3 py-2 rounded-lg border border-violet-200 bg-white
                     focus:outline-none focus:ring-2 focus:ring-violet-400/40 resize-none"></textarea>
            <div class="flex items-center justify-between mt-2">
              <p v-if="aiError" class="text-[11px] text-rose-600 font-medium">{{ aiError }}</p>
              <span v-else class="text-[10px] text-gray-400">Le contenu actuel sera remplacé.</span>
              <button @click="runAi" type="button" :disabled="aiLoading || !aiPrompt.trim()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold text-white
                       bg-gradient-to-r from-violet-500 to-fuchsia-500 hover:opacity-90 disabled:opacity-40 transition">
                <svg v-if="aiLoading" class="animate-spin" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <path stroke-linecap="round" :d="iconPath('spinner')"/>
                </svg>
                {{ aiLoading ? 'Rédaction…' : 'Rédiger' }}
              </button>
            </div>
          </div>
          </Transition>
        </div>
        <div class="p-5 space-y-2.5 overflow-y-auto">
          <!-- Destinataires (À) en chips + autocomplete style O365 -->
          <div class="relative">
            <div :class="chipCls" @click="$refs.toInput.focus()">
              <span class="text-xs font-bold text-gray-400 shrink-0 mt-1">À</span>
              <span v-for="(c, i) in compose.to" :key="'to'+i"
                    :class="['chip', validEmail(c) ? 'chip-ok' : 'chip-bad']">
                {{ c }}
                <button type="button" @click.stop="compose.to.splice(i, 1)"
                  class="chip-x">×</button>
              </span>
              <input ref="toInput" v-model="toDraft" type="text"
                :placeholder="compose.to.length ? '' : 'Tape un nom ou une adresse…'"
                @input="onSuggestInput('to', $event.target.value)"
                @keydown="onSuggestKey($event, 'to', compose.to, 'toDraft')"
                @blur="onSuggestBlur('to', compose.to, 'toDraft')"
                @paste="onChipPaste($event, compose.to, 'toDraft')"
                class="flex-1 min-w-[160px] outline-none border-0 bg-transparent text-sm py-1"
                autocomplete="off"/>
            </div>
            <SuggestPopup v-if="suggestField === 'to' && suggestItems.length"
              :items="suggestItems" :active="suggestActive"
              @pick="pickSuggest('to', compose.to, 'toDraft', $event)"
              @hover="suggestActive = $event"/>
          </div>

          <!-- Destinataires (Cc) en chips + autocomplete -->
          <div class="relative">
            <div :class="chipCls" @click="$refs.ccInput.focus()">
              <span class="text-xs font-bold text-gray-400 shrink-0 mt-1">Cc</span>
              <span v-for="(c, i) in compose.cc" :key="'cc'+i"
                    :class="['chip', validEmail(c) ? 'chip-ok' : 'chip-bad']">
                {{ c }}
                <button type="button" @click.stop="compose.cc.splice(i, 1)"
                  class="chip-x">×</button>
              </span>
              <input ref="ccInput" v-model="ccDraft" type="text"
                :placeholder="compose.cc.length ? '' : 'Cc (optionnel)'"
                @input="onSuggestInput('cc', $event.target.value)"
                @keydown="onSuggestKey($event, 'cc', compose.cc, 'ccDraft')"
                @blur="onSuggestBlur('cc', compose.cc, 'ccDraft')"
                @paste="onChipPaste($event, compose.cc, 'ccDraft')"
                class="flex-1 min-w-[160px] outline-none border-0 bg-transparent text-sm py-1"
                autocomplete="off"/>
            </div>
            <SuggestPopup v-if="suggestField === 'cc' && suggestItems.length"
              :items="suggestItems" :active="suggestActive"
              @pick="pickSuggest('cc', compose.cc, 'ccDraft', $event)"
              @hover="suggestActive = $event"/>
          </div>

          <input v-model="compose.subject" type="text" placeholder="Objet" :class="inputCls"/>

          <!-- Barre d'outils éditeur — façon Gmail -->
          <div class="flex flex-wrap items-center gap-x-1 gap-y-1 px-2 py-1.5 border border-gray-200 rounded-t-xl bg-gray-50 text-gray-700">
            <button @mousedown.prevent="format('undo')" title="Annuler" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('reply')"/></svg>
            </button>
            <button @mousedown.prevent="format('redo')" title="Rétablir" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('forward')"/></svg>
            </button>
            <span :class="tbSep"></span>
            <select @change="setFont($event.target.value)" :class="tbSelect" title="Police">
              <option value="" disabled selected>Sans Serif</option>
              <option value="-apple-system, Segoe UI, Roboto, Arial, sans-serif">Sans Serif</option>
              <option value="Georgia, serif">Serif</option>
              <option value="ui-monospace, SFMono-Regular, Consolas, monospace">Monospace</option>
              <option value="Comic Sans MS, cursive">Comic Sans</option>
            </select>
            <select @change="setSize($event.target.value)" :class="tbSelect" title="Taille">
              <option value="" disabled selected>Taille</option>
              <option value="1">Petite</option>
              <option value="3">Normale</option>
              <option value="5">Grande</option>
              <option value="7">Énorme</option>
            </select>
            <span :class="tbSep"></span>
            <button @mousedown.prevent="format('bold')" title="Gras" :class="tbBtn"><b>B</b></button>
            <button @mousedown.prevent="format('italic')" title="Italique" :class="tbBtn"><i style="font-family:serif">I</i></button>
            <button @mousedown.prevent="format('underline')" title="Souligné" :class="tbBtn"><u>U</u></button>
            <label :class="tbBtn" title="Couleur du texte" class="cursor-pointer relative">
              <span class="font-bold text-[13px]">A</span>
              <span class="absolute bottom-1 left-1.5 right-1.5 h-1 rounded" :style="{ background: textColor }"></span>
              <input type="color" v-model="textColor" @input="applyColor"
                class="absolute inset-0 opacity-0 cursor-pointer"/>
            </label>
            <span :class="tbSep"></span>
            <button @mousedown.prevent="format('justifyLeft')" title="Aligner à gauche" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="iconPath('alignLeft')"/></svg>
            </button>
            <button @mousedown.prevent="format('justifyCenter')" title="Centrer" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="iconPath('alignCenter')"/></svg>
            </button>
            <button @mousedown.prevent="format('justifyRight')" title="Aligner à droite" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="iconPath('alignRight')"/></svg>
            </button>
            <span :class="tbSep"></span>
            <button @mousedown.prevent="format('insertOrderedList')" title="Liste numérotée" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="iconPath('listNumbered')"/></svg>
            </button>
            <button @mousedown.prevent="format('insertUnorderedList')" title="Liste à puces" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5" cy="6" r="1.2" fill="currentColor"/><circle cx="5" cy="12" r="1.2" fill="currentColor"/><circle cx="5" cy="18" r="1.2" fill="currentColor"/><path stroke-linecap="round" :d="iconPath('listBullet')"/></svg>
            </button>
            <button @mousedown.prevent="format('outdent')" title="Diminuer le retrait" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="iconPath('outdent')"/></svg>
            </button>
            <button @mousedown.prevent="format('indent')" title="Augmenter le retrait" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="iconPath('indent')"/></svg>
            </button>
            <span :class="tbSep"></span>
            <button @mousedown.prevent="format('formatBlock', 'blockquote')" title="Citation" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path :d="iconPath('quote')"/></svg>
            </button>
            <button @mousedown.prevent="format('strikeThrough')" title="Barré" :class="tbBtn"><s>S</s></button>
            <button @mousedown.prevent="format('removeFormat')" title="Effacer la mise en forme" :class="tbBtn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="iconPath('clearFmt')"/></svg>
            </button>
          </div>
          <!-- Éditeur HTML -->
          <div ref="editorRef" contenteditable="true"
               @paste="onEditorPaste" @drop.prevent="onEditorDrop" @dragover.prevent
               @input="composeDirty = true"
               @mouseup="cacheSelection" @keyup="cacheSelection" @blur="cacheSelection"
               :class="['border border-t-0 border-gray-200 rounded-b-xl px-3.5 py-3 text-sm leading-relaxed',
                        'overflow-y-auto focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/30',
                        composeMax ? 'min-h-[55vh] max-h-[70vh]' : 'min-h-[220px] max-h-[40vh]']"></div>

          <!-- Pièces jointes attachées (chips) -->
          <Transition name="panel-down">
          <div v-if="attachedFiles.length" class="pt-2 space-y-2">
            <TransitionGroup name="chip-in" tag="div" class="flex flex-wrap gap-2 items-center">
              <span v-for="(f,i) in attachedFiles" :key="f.name + f.size"
                    class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg bg-[#FF4D2E]/10 text-[#FF4D2E] font-medium">
                📎 {{ f.name }} <span class="text-[#FF4D2E]/60">{{ fmtSize(f.size) }}</span>
                <button @click="attachedFiles.splice(i,1)" class="ml-0.5 hover:text-[#df3c1f] font-bold">×</button>
              </span>
            </TransitionGroup>

            <!-- Mode de livraison + jauge -->
            <div class="flex flex-wrap items-center gap-2 p-2 rounded-xl border border-gray-200 bg-gray-50/70">
              <div class="flex rounded-full bg-white border border-gray-200 overflow-hidden text-[11px] font-bold">
                <button type="button" @click="storageMode='smtp'" :disabled="overSmtp"
                  :class="['px-3 py-1.5 transition flex items-center gap-1.5',
                           storageMode==='smtp' ? 'bg-[#FF4D2E] text-white' : 'text-gray-600 hover:bg-gray-100',
                           overSmtp ? 'opacity-40 cursor-not-allowed' : '']"
                  :title="overSmtp ? 'Dépasse 10 Mo : utilisez le stockage Noliae' : 'Envoi direct en pièce jointe'">
                  ✉️ Email <span class="font-normal opacity-70">10 Mo</span>
                </button>
                <button type="button" @click="storageMode='s3'"
                  :class="['px-3 py-1.5 transition flex items-center gap-1.5',
                           storageMode==='s3' ? 'bg-[#FF4D2E] text-white' : 'text-gray-600 hover:bg-gray-100']"
                  title="Stockage Noliae avec lien à durée limitée">
                  ☁️ Noliae <span class="font-normal opacity-70">1 Go</span>
                </button>
              </div>

              <div v-if="storageMode === 's3'" class="flex items-center gap-1.5 text-[11px] text-gray-600">
                Lien valable
                <select v-model.number="ttlDays" class="text-[11px] rounded border border-gray-300 px-2 py-1 bg-white font-bold">
                  <option :value="1">1 jour</option>
                  <option :value="3">3 jours</option>
                  <option :value="7">7 jours</option>
                </select>
              </div>

              <div class="ml-auto flex items-center gap-2 min-w-[140px]">
                <div class="flex-1 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                  <div :class="['h-full transition-all',
                                overLimit ? 'bg-rose-500' : 'bg-emerald-500']"
                       :style="{ width: Math.min(100, sizePct) + '%' }"></div>
                </div>
                <span :class="['text-[10.5px] font-bold tabular-nums whitespace-nowrap',
                               overLimit ? 'text-rose-600' : 'text-gray-500']">
                  {{ fmtSize(totalAttachSize) }} / {{ storageMode === 's3' ? '1 Go' : '10 Mo' }}
                </span>
              </div>
            </div>
            <p v-if="overLimit" class="text-[11px] text-rose-600 font-medium">
              {{ storageMode === 's3'
                ? 'Vous dépassez 1 Go : retirez des fichiers.'
                : 'Vous dépassez 10 Mo en mode email : passez au stockage Noliae.' }}
            </p>
          </div>
          </Transition>
          <input ref="fileInput" type="file" multiple class="hidden" @change="addFiles"/>
        </div>
        <!-- Barre de progression upload -->
        <div v-if="sending && attachedFiles.length" class="px-5 pt-2">
          <div class="flex items-center justify-between text-[11px] mb-1">
            <span class="font-bold text-gray-600">
              {{ uploadPct >= 100 ? 'Envoi sur S3 terminé…' : 'Téléversement des pièces jointes' }}
            </span>
            <span class="text-gray-400 tabular-nums">{{ uploadPct }} %</span>
          </div>
          <div class="h-1.5 rounded-full bg-gray-200 overflow-hidden">
            <div class="h-full bg-gradient-to-r from-[#FF4D2E] to-orange-500 transition-all duration-200"
                 :style="{ width: uploadPct + '%' }"></div>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 px-3 sm:px-4 py-2.5 border-t border-gray-100 bg-gray-50">
          <!-- Bouton Envoyer principal -->
          <div class="inline-flex rounded-full bg-[#FF4D2E] shadow-sm hover:shadow transition disabled:opacity-40"
               :class="overLimit ? 'opacity-40 pointer-events-none' : ''">
          <button @click="sendMail" :disabled="sending || overLimit"
            class="flex items-center gap-2 pl-5 pr-4 py-2 rounded-l-full text-white text-sm font-bold
                   hover:bg-[#df3c1f] transition">
            <svg v-if="!sending" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('sent')"/>
            </svg>
            <svg v-else class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path stroke-linecap="round" :d="iconPath('spinner')"/>
            </svg>
            {{ sending ? 'Envoi…' : (scheduleAt ? 'Planifier' : 'Envoyer') }}
          </button>
          <button type="button" @click="scheduleOpen = !scheduleOpen" title="Planifier l'envoi"
            class="w-9 py-2 rounded-r-full border-l border-white/20 text-white hover:bg-[#df3c1f] flex items-center justify-center">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
          </button>
          </div>
          <Transition name="pop">
          <div v-if="scheduleOpen" class="absolute bottom-full mb-2 left-0 z-20 w-72 bg-white border border-gray-200 rounded-xl shadow-2xl p-3">
            <p class="text-[11px] font-bold text-gray-700 mb-2 uppercase tracking-widest">⏰ Planifier l'envoi</p>
            <button v-for="o in scheduleOptions" :key="o.label" @click="pickSchedule(o.hours)"
              class="w-full text-left text-sm px-3 py-1.5 rounded-md hover:bg-gray-50 flex justify-between">
              <span>{{ o.label }}</span>
              <span class="text-gray-400 text-[11px] tabular-nums">{{ o.preview }}</span>
            </button>
            <input v-model="scheduleAt" type="datetime-local"
              class="mt-2 w-full text-sm rounded-lg border border-gray-300 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#FF4D2E]/40"/>
            <button v-if="scheduleAt" @click="scheduleAt=''; scheduleOpen=false" class="mt-1 text-[10px] text-gray-400 hover:text-rose-600">Effacer</button>
          </div>
          </Transition>

          <!-- Actions secondaires (style Gmail) -->
          <div class="flex items-center gap-0.5 ml-1">
            <button @click="$refs.fileInput.click()" type="button" title="Joindre un fichier" :class="fbBtn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('paperclip')"/>
              </svg>
            </button>
            <button @click="makeLink" type="button" title="Insérer un lien" :class="fbBtn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('link')"/>
              </svg>
            </button>
            <div class="relative">
              <button @click="emojiOpen = !emojiOpen" type="button" title="Émojis" :class="fbBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                  <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9 10h.01M15 10h.01M8.5 14.5c1 1.5 2.5 2 3.5 2s2.5-.5 3.5-2"/>
                </svg>
              </button>
              <Transition name="pop">
              <div v-if="emojiOpen" class="absolute bottom-full mb-2 left-0 z-10 bg-white border border-gray-200 rounded-xl shadow-xl p-2 grid grid-cols-8 gap-0.5 w-56">
                <button v-for="e in emojis" :key="e" type="button" @click="insertText(e); emojiOpen=false"
                  class="w-6 h-6 hover:bg-gray-100 rounded text-base leading-none">{{ e }}</button>
              </div>
              </Transition>
            </div>
            <button @click="$refs.imgInput.click()" type="button" title="Insérer une image"
                    :class="fbBtn" :disabled="imgUploading">
              <svg v-if="!imgUploading" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path stroke-linecap="round" d="M21 16l-5-5-9 8"/>
              </svg>
              <svg v-else class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" :d="iconPath('spinner')"/>
              </svg>
            </button>
            <input ref="imgInput" type="file" accept="image/*" multiple class="hidden"
              @change="onPickImages($event.target.files); $event.target.value=''"/>
            <button @click="signBody = !signBody" type="button"
              :title="signBody ? 'Message signé PGP' : 'Signer le message PGP'"
              :class="[fbBtn, signBody ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : '']">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('pencil')"/>
              </svg>
            </button>
            <button @click="encryptBody = !encryptBody" type="button"
              :title="encryptBody ? 'Chiffrer ce message PGP' : 'Chiffrement PGP désactivé'"
              :class="[fbBtn, encryptBody ? 'bg-violet-100 text-violet-700 hover:bg-violet-200' : '']">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('lock')"/>
              </svg>
            </button>
            <button @click="askReceipt = !askReceipt" type="button"
              :title="askReceipt ? 'Accusé de réception demandé' : 'Demander un accusé de réception'"
              :class="[fbBtn, askReceipt ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : '']">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('shieldCheck')"/>
              </svg>
            </button>
            <button @click="insertSignature" type="button" title="Insérer la signature" :class="fbBtn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('pencil')"/>
              </svg>
            </button>
          </div>

          <span class="text-[11px] ml-auto truncate flex items-center gap-2">
            <span v-if="draftStatus === 'saving'" class="text-gray-400">Enregistrement…</span>
            <span v-else-if="draftStatus === 'saved'" class="text-emerald-600 font-semibold">✓ Brouillon enregistré</span>
            <span v-else-if="draftStatus === 'error'" class="text-rose-500">⚠ Brouillon non enregistré</span>
            <span class="text-gray-400 hidden sm:inline">De : {{ me }}</span>
          </span>
        </div>
      </div>
    </div>
    </Transition>

    <!-- Menu contextuel clic-droit (style Gmail) -->
    <Transition name="pop">
      <div v-if="ctx" class="fixed z-50 bg-white rounded-xl shadow-2xl border border-gray-200 py-1.5 min-w-[260px]"
           :style="{ top: ctx.y + 'px', left: ctx.x + 'px' }"
           @click.stop>
        <button v-for="item in ctxItems" :key="item.label"
          @click="item.action(); ctx = null"
          :class="['w-full flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-50 transition text-left',
                   item.tone === 'danger' ? 'text-rose-600' : 'text-gray-700']">
          <span class="w-4 h-4 flex items-center justify-center shrink-0" v-html="item.icon"></span>
          <span class="flex-1">{{ item.label }}</span>
          <span v-if="item.right" class="text-gray-400 text-xs">{{ item.right }}</span>
        </button>
      </div>
    </Transition>

    <!-- Modale Détails expéditeur (style Gmail) -->
    <Transition name="modal">
      <div v-if="senderInfoOpen && selected" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="senderInfoOpen=false">
        <div class="compose-panel bg-white w-full sm:max-w-lg rounded-2xl shadow-2xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h2 class="font-bold text-sm">Détails du message</h2>
            <button @click="senderInfoOpen=false" class="w-7 h-7 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 text-xl leading-none flex items-center justify-center">&times;</button>
          </div>
          <div class="p-5 text-sm space-y-2.5">
            <div class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">De :</span>
              <span class="col-span-2 font-bold text-gray-900">{{ selected.from || selected.from_mail }}<br><span class="font-normal text-gray-500 text-[12px]">&lt;{{ selected.from_mail }}&gt;</span></span>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">À :</span>
              <span class="col-span-2 text-gray-700 break-all">{{ selected.to }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">Date :</span>
              <span class="col-span-2 text-gray-700">{{ fmtFull(selected.date) }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">Objet :</span>
              <span class="col-span-2 text-gray-700">{{ selected.subject }}</span>
            </div>
            <div v-if="selected.auth?.dkim" class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">Signé par :</span>
              <span class="col-span-2 text-gray-700 font-mono text-[12px]">
                {{ selected.auth.dkim === 'pass' ? '✓ ' + (selected.from_mail.split('@')[1] || '?') : '⚠ DKIM ' + selected.auth.dkim }}
              </span>
            </div>
            <div v-if="selected.auth?.spf" class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">SPF :</span>
              <span class="col-span-2 text-gray-700 font-mono text-[12px]">
                {{ selected.auth.spf === 'pass' ? '✓ Autorisé' : '⚠ ' + selected.auth.spf }}
              </span>
            </div>
            <div v-if="selected.auth?.dmarc" class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">DMARC :</span>
              <span class="col-span-2 text-gray-700 font-mono text-[12px]">
                {{ selected.auth.dmarc === 'pass' ? '✓ Conforme' : '⚠ ' + selected.auth.dmarc }}
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-gray-100">
              <span class="text-gray-400 font-bold text-right">Sécurité :</span>
              <span class="col-span-2 text-emerald-700 text-[12px] flex items-center gap-1.5">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('shieldTLS')"/></svg>
                Chiffrement standard TLS 1.3
              </span>
            </div>
            <div v-if="selected.message_id" class="grid grid-cols-3 gap-2">
              <span class="text-gray-400 font-bold text-right">Message-ID :</span>
              <span class="col-span-2 text-gray-500 font-mono text-[11px] break-all">{{ selected.message_id }}</span>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Modale Raccourcis clavier -->
    <Transition name="modal">
      <div v-if="shortcutsOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="shortcutsOpen=false">
        <div class="compose-panel bg-white w-full sm:max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3" style="background:#15151a">
            <h2 class="font-bold text-white text-sm flex items-center gap-2">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path stroke-linecap="round" :d="iconPath('keyboard')"/></svg>
              Raccourcis clavier Noliae Mail
            </h2>
            <button @click="shortcutsOpen=false" class="w-8 h-8 rounded-md text-gray-400 hover:text-white hover:bg-white/10 text-2xl leading-none flex items-center justify-center">&times;</button>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1 max-h-[70vh] overflow-y-auto">
            <div v-for="g in shortcutGroups" :key="g.title" class="mb-3">
              <p class="text-[10px] font-black uppercase tracking-widest text-noliae-pulse mb-2 mt-1">{{ g.title }}</p>
              <div v-for="s in g.items" :key="s.label" class="flex items-center justify-between py-1.5 text-sm border-b border-gray-50 last:border-0">
                <span class="text-gray-700">{{ s.label }}</span>
                <span class="flex items-center gap-1">
                  <kbd v-for="(k, i) in s.keys" :key="i"
                       class="px-2 py-0.5 rounded-md bg-gray-100 border border-gray-200 text-[11px] font-bold text-gray-700 shadow-sm">{{ k }}</kbd>
                </span>
              </div>
            </div>
          </div>
          <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 text-[11px] text-gray-500 flex items-center justify-between">
            <span>💡 Les raccourcis sont désactivés quand tu écris (input / éditeur).</span>
            <span>Astuce : appuie <kbd class="px-1.5 py-0.5 rounded bg-white border border-gray-200 font-bold">?</kbd> pour rouvrir.</span>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Modale Paramètres -->
    <Transition name="modal">
      <div v-if="settingsOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="compose-panel bg-white w-full sm:max-w-xl rounded-2xl shadow-2xl flex flex-col overflow-hidden max-h-[90vh]">
          <div class="flex items-center justify-between px-5 py-3" style="background:#15151a">
            <h2 class="font-bold text-white text-sm">Paramètres webmail</h2>
            <button @click="settingsOpen = false" class="w-8 h-8 rounded-md text-gray-400 hover:text-white hover:bg-white/10 text-2xl leading-none flex items-center justify-center">&times;</button>
          </div>
          <div class="p-5 space-y-4 overflow-y-auto">
            <div>
              <label class="text-xs font-black text-gray-900 block mb-1.5">Signature HTML</label>
              <p class="text-[11px] text-gray-500 mb-2">Insérée automatiquement à la fin de chaque nouveau message. Le HTML simple est accepté (<code>&lt;br&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;a&gt;</code>, etc.).</p>
              <textarea v-model="settingsForm.signature_html" rows="8"
                placeholder="Cordialement,&#10;&lt;strong&gt;Bastien LANGUEDOC&#10;&lt;/strong&gt;&lt;br&gt;Noliae · bastien@noliae.net"
                class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/30"></textarea>
              <div v-if="settingsForm.signature_html" class="mt-2 p-3 rounded-xl bg-gray-50 border border-gray-100 text-[13px]">
                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-bold mb-1">Aperçu</p>
                <div v-html="settingsForm.signature_html"></div>
              </div>
            </div>
            <!-- Affichage : liste / conversation -->
            <div class="pt-3 border-t border-gray-100">
              <h3 class="text-xs font-black text-gray-900 mb-2">Affichage des messages</h3>
              <div class="flex gap-2">
                <button type="button" @click="settingsForm.view_mode = 'list'"
                  :class="['flex-1 px-3 py-2 rounded-xl border text-[12px] font-bold transition',
                           settingsForm.view_mode === 'list' ? 'bg-[#FF4D2E]/10 border-[#FF4D2E] text-[#FF4D2E]' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50']">
                  📋 Liste — 1 ligne par mail
                </button>
                <button type="button" @click="settingsForm.view_mode = 'thread'"
                  :class="['flex-1 px-3 py-2 rounded-xl border text-[12px] font-bold transition',
                           settingsForm.view_mode === 'thread' ? 'bg-[#FF4D2E]/10 border-[#FF4D2E] text-[#FF4D2E]' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50']">
                  💬 Conversation — groupé par sujet
                </button>
              </div>
            </div>

            <label class="flex items-start gap-2 cursor-pointer">
              <input type="checkbox" v-model="settingsForm.ask_receipts"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-[#FF4D2E] focus:ring-[#FF4D2E]/30"/>
              <span class="text-sm">
                <span class="font-bold text-gray-900">Demander un accusé de réception</span>
                <span class="block text-[11px] text-gray-500">Par défaut sur tous les nouveaux mails (peut être basculé message par message).</span>
              </span>
            </label>

            <!-- Règles utilisateur -->
            <div class="pt-3 border-t border-gray-100">
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-xs font-black text-gray-900">Règles automatiques</h3>
                <button type="button" @click="addRule"
                  class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#FF4D2E] text-white hover:bg-[#df3c1f]">+ Règle</button>
              </div>
              <p class="text-[11px] text-gray-500 mb-2">
                Si l'expéditeur ou le sujet correspond, applique automatiquement une couleur ou marque comme favori.
              </p>
              <div v-if="!settingsForm.rules?.length" class="text-[11px] text-gray-400 text-center py-4">Aucune règle. Clic « + Règle » pour en créer une.</div>
              <div v-for="(r, i) in settingsForm.rules" :key="r.id || i"
                   class="p-2.5 rounded-xl border border-gray-200 bg-white space-y-1.5 mb-2">
                <div class="flex items-center gap-1.5">
                  <input v-model="r.name" type="text" placeholder="Nom (ex: Factures)"
                    class="flex-1 text-[12px] font-bold rounded-md border border-gray-200 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#FF4D2E]/40"/>
                  <button @click="settingsForm.rules.splice(i, 1)" type="button"
                    class="text-rose-500 hover:text-rose-700 text-lg leading-none px-1">×</button>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                  <input v-model="r.match.from" placeholder="De contient…" class="text-[11px] rounded-md border border-gray-200 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#FF4D2E]/40"/>
                  <input v-model="r.match.subject" placeholder="Sujet contient…" class="text-[11px] rounded-md border border-gray-200 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#FF4D2E]/40"/>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                  <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Action →</span>
                  <button v-for="c in LABEL_LIST" :key="c" type="button" @click="r.action.color = (r.action.color === c ? null : c)"
                    :title="c"
                    :class="['w-5 h-5 rounded-full border-2', r.action.color === c ? 'border-gray-900' : 'border-white shadow']"
                    :style="{ background: LABEL_COLORS[c] }"></button>
                  <label class="flex items-center gap-1 text-[11px] font-bold text-gray-700 cursor-pointer ml-auto">
                    <input type="checkbox" v-model="r.action.star" class="w-3.5 h-3.5 rounded border-gray-300 text-amber-400 focus:ring-amber-300"/>
                    ★ Favori
                  </label>
                </div>
              </div>
            </div>

            <!-- Sécurité PGP -->
            <div class="pt-3 border-t border-gray-100">
              <h3 class="text-xs font-black text-gray-900 mb-1 flex items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('lock')"/></svg>
                Sécurité · Chiffrement PGP
              </h3>
              <p class="text-[11px] text-gray-500 mb-3">
                La clé est générée <strong>dans ton navigateur</strong>, jamais transmise en clair.
                La clé privée est protégée par une passphrase. Tout est conforme OpenPGP (RFC 4880).
              </p>

              <div v-if="!settingsForm.pgp_fingerprint" class="p-3 rounded-xl bg-gray-50 border border-gray-200 space-y-2">
                <p class="text-[12px] text-gray-700">Tu n'as pas encore de clé PGP.</p>
                <input v-model="pgpPass" type="password" placeholder="Choisis une passphrase (mémorise-la !)"
                  class="w-full text-sm rounded-lg border border-gray-300 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/30"/>
                <p v-if="pgpError" class="text-[11px] text-rose-600">{{ pgpError }}</p>
                <button @click="generatePgp" :disabled="pgpBusy || pgpPass.length < 8"
                  class="px-3 py-1.5 rounded-full text-[12px] font-bold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-40 transition">
                  {{ pgpBusy ? 'Génération…' : '🔐 Générer ma paire de clés PGP' }}
                </button>
                <p class="text-[10px] text-gray-400">Algorithme : Curve25519 (ECC moderne, rapide).</p>
              </div>

              <div v-else class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 space-y-2.5">
                <div class="flex items-center gap-2 text-[12px] text-emerald-800">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12.75 11.25 15 15 9.75 21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                  <span class="font-bold">Clé PGP active</span>
                </div>
                <p class="text-[10.5px] font-mono text-emerald-900 break-all">{{ settingsForm.pgp_fingerprint }}</p>
                <div class="flex flex-wrap gap-1.5">
                  <button @click="downloadPgp('public')"
                    class="px-2.5 py-1 rounded-md bg-white border border-emerald-300 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100">
                    ⬇ Clé publique (.asc)
                  </button>
                  <button @click="downloadPgp('private')"
                    class="px-2.5 py-1 rounded-md bg-white border border-amber-300 text-[11px] font-bold text-amber-800 hover:bg-amber-50">
                    ⬇ Clé privée (chiffrée)
                  </button>
                  <button @click="revokePgp"
                    class="px-2.5 py-1 rounded-md bg-white border border-rose-300 text-[11px] font-bold text-rose-700 hover:bg-rose-50 ml-auto">
                    Révoquer
                  </button>
                </div>
                <label class="flex items-start gap-2 cursor-pointer pt-1">
                  <input type="checkbox" v-model="settingsForm.pgp_attach_public"
                    class="mt-0.5 w-4 h-4 rounded border-gray-300 text-[#FF4D2E] focus:ring-[#FF4D2E]/30"/>
                  <span class="text-[12px]">
                    <span class="font-bold text-gray-900">Joindre automatiquement ma clé publique</span>
                    <span class="block text-[10.5px] text-gray-500">Ajoute <code>noliae-pgp-public-key.asc</code> en pièce jointe à chaque envoi (façon ProtonMail).</span>
                  </span>
                </label>
                <p class="text-[10.5px] text-gray-500 border-t border-emerald-200 pt-2">
                  📡 Ta clé publique est aussi accessible à <code class="text-[10px]">/webmail/pgp/&lt;sha256(email)&gt;.asc</code> pour permettre à n'importe quel correspondant Noliae de t'envoyer du chiffré.
                </p>
              </div>

              <!-- Annuaire de contacts PGP -->
              <div class="mt-3 p-3 rounded-xl bg-gray-50 border border-gray-200 space-y-2">
                <p class="text-[11px] font-bold text-gray-900">📇 Clé publique d'un contact</p>
                <p class="text-[10.5px] text-gray-500">
                  Colle un bloc <code>BEGIN PGP PUBLIC KEY BLOCK</code> + l'adresse, ou laisse vide pour <strong>chercher</strong> sur keys.openpgp.org.
                </p>
                <input v-model="pgpImportEmail" type="email" placeholder="contact@exemple.com"
                  class="w-full text-sm rounded-lg border border-gray-300 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/30"/>
                <textarea v-model="pgpImportArmored" rows="3"
                  placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----&#10;…&#10;-----END PGP PUBLIC KEY BLOCK-----"
                  class="w-full text-[11px] font-mono rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/30"></textarea>
                <div class="flex flex-wrap gap-2 items-center">
                  <button @click="pgpManualImport" :disabled="pgpImportBusy || !pgpImportEmail || !pgpImportArmored"
                    class="px-3 py-1.5 rounded-full bg-violet-600 text-white text-[11px] font-bold hover:bg-violet-700 disabled:opacity-40">
                    📥 Importer la clé
                  </button>
                  <button @click="pgpKeyserverLookup" :disabled="pgpImportBusy || !pgpImportEmail"
                    class="px-3 py-1.5 rounded-full bg-white border border-gray-300 text-gray-700 text-[11px] font-bold hover:bg-gray-100 disabled:opacity-40">
                    🔍 Chercher sur keys.openpgp.org
                  </button>
                  <span v-if="pgpImportMsg" class="text-[11px]" :class="pgpImportMsg.ok ? 'text-emerald-700' : 'text-rose-600'">
                    {{ pgpImportMsg.text }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100 bg-gray-50">
            <button @click="settingsOpen = false" class="px-4 py-2 rounded-lg text-gray-600 text-sm font-medium hover:bg-gray-200 transition">Annuler</button>
            <button @click="saveSettings" :disabled="settingsBusy"
              class="px-5 py-2 rounded-full bg-[#FF4D2E] text-white text-sm font-bold hover:bg-[#df3c1f] disabled:opacity-40 transition">
              {{ settingsBusy ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Indicateur de stockage -->
    <transition name="quota">
      <div v-if="quota" class="fixed bottom-3 right-3 z-30 select-none"
           :title="fmtBytes(quota.used) + ' / ' + fmtBytes(quota.limit) + ' — ' + fmtBytes(Math.max(0, quota.limit - quota.used)) + ' libres'">
        <div class="quota-card flex items-center gap-2 bg-white rounded-full shadow-md
                    border border-gray-100 pl-1.5 pr-3 py-1">
          <div class="relative w-7 h-7 shrink-0">
            <svg width="28" height="28" viewBox="0 0 48 48" class="-rotate-90">
              <circle cx="24" cy="24" r="20" fill="none" stroke="#f1f1f3" stroke-width="6"/>
              <circle cx="24" cy="24" r="20" fill="none" :stroke="quotaColor" stroke-width="6"
                      stroke-linecap="round" :stroke-dasharray="RING_C"
                      :stroke-dashoffset="quotaOffset"/>
            </svg>
          </div>
          <span class="text-[11px] font-bold text-gray-700 tabular-nums">
            {{ fmtBytes(quotaUsedAnim) }}<span class="text-gray-300"> / </span>{{ fmtBytes(quota.limit) }}
          </span>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SuggestPopup from '../Components/SuggestPopup.vue';

/** Logout via POST (la route GET a été retirée — CSRF safety). */
function logout() { router.post('/logout'); }

/** Identity switcher : ma boîte ou une boîte partagée. */
const identityOpen = ref(false);
const asSharedId = computed(() => {
  const url = new URL(window.location.href);
  const v = url.searchParams.get('as') || (typeof props !== 'undefined' ? props.as_shared_id : null);
  return v ? parseInt(v) : null;
});
const currentIdentity = computed(() => {
  if (asSharedId.value && props.shared_mailboxes?.length) {
    const s = props.shared_mailboxes.find(x => x.id === asSharedId.value);
    if (s) return { label: s.display_name, hash: '' };
  }
  return { label: props.me_name || props.me, hash: props.me_hash };
});

// Petit click-outside global pour fermer le dropdown
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value(); };
    document.addEventListener('click', el._clickOutside);
  },
  unmounted(el) { document.removeEventListener('click', el._clickOutside); },
};

const props = defineProps({
  me: String,
  me_hash: String,
  me_name: String,
  folders: { type: Array, default: () => [] },
  folder: String,
  messages: { type: Array, default: () => [] },
  selected: Object,
  quota: { type: Object, default: null },
  settings: { type: Object, default: () => ({ signature_html: '', ask_receipts: false }) },
  total: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  per_page: { type: Number, default: 40 },
  search: { type: String, default: '' },
  error: String,
  enable_noliae_ai: { type: Boolean, default: false },
  shared_mailboxes: { type: Array, default: () => [] }, // boîtes partagées accessibles
  as_shared_id: { type: Number, default: null },        // id de la boîte partagée actuellement vue
});

const inputCls = 'w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm '
  + 'focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/30 focus:border-[#FF4D2E]/40';
const chipCls = 'flex flex-wrap items-start gap-1.5 w-full rounded-xl border border-gray-200 '
  + 'px-3 py-1.5 text-sm cursor-text focus-within:ring-2 focus-within:ring-[#FF4D2E]/30 '
  + 'focus-within:border-[#FF4D2E]/40';

// Classes utilitaires de la barre d'outils
const tbBtn = 'w-8 h-8 rounded-md hover:bg-gray-200 flex items-center justify-center text-[13px] transition';
const tbSep = 'inline-block w-px h-5 bg-gray-300 mx-1';
const tbSelect = 'h-7 px-2 rounded-md border border-transparent hover:border-gray-300 bg-white text-[12px] cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#FF4D2E]/30';
const fbBtn = 'w-9 h-9 rounded-full text-gray-500 hover:bg-gray-200 flex items-center justify-center transition';

const emojis = ['😀','😅','😂','🤣','😊','😍','🥰','😎','🤔','😴','🙄','😢','😡','👍','👎','👏','🙏','💪','🔥','💯','✨','🎉','❤️','💔','✅','❌','⚠️','📎','📅','📧','💡','🚀'];

const LABEL_COLORS = {
  red:    '#ef4444',
  orange: '#f97316',
  yellow: '#eab308',
  green:  '#10b981',
  blue:   '#3b82f6',
  violet: '#8b5cf6',
};
const LABEL_LIST = Object.keys(LABEL_COLORS);

/* Application des règles utilisateur + mode conversation */
/* Catégorisation des mails (heuristiques renforcées avec signaux RFC).
   Ordre de priorité : signaux serveur d'abord (List-Unsubscribe, Precedence),
   puis domaines/mots-clés. Le tout est cumulatif via un score par catégorie. */
const SOCIAL_DOMAINS = /(facebook|fb|messenger|twitter|x|linkedin|instagram|tiktok|snapchat|pinterest|youtube|reddit|discord|slack|mastodon|bluesky|threads|whatsapp|telegram|signal|github\.com.notification|gitlab\.com.notification)\.(com|net|fr|io|me|org|co)/i;
const NOTIF_FROM    = /(no.?reply|noreply|notifications?|alerts?|automated|do.?not.?reply|do_not_reply|postmaster|mailer.?daemon|bounce|return|delivery|verification|account|security|admin|root|cron|monitoring|hooks?@|webhooks?@)/i;
const NOTIF_SUBJ    = /(notification|alerte|sécurité|security|verification|2fa|confirmation|reçu|receipt|invoice|facture|bill|payment|paiement|order|commande|tracking|expédition|shipped|delivery|tickets?|jira|asana|trello|github|gitlab|backup|sauvegarde|rapport quotidien|daily report|cron|status|monitoring|incident)/i;
const PROMO_SUBJ    = /(newsletter|promo|offer|sale|reduction|deal|discount|vente|black.?friday|cyber.?monday|free.?trial|coupon|rabais|soldes|prix|gratuit|free|– *%|% *off|early bird|don't miss|ne manquez|découvre|profitez|exclusive|exclusif|webinar|webinaire|join now|inscrivez|register|s'inscrire)/i;
const PROMO_FROM    = /^(news|info|hello|marketing|deals?|offers?|update|contact|community|team|crew|hi|hey|store|shop|sales?|hello|onboarding|growth|product|members?|club|family|insider|noreply)@/i;
const BIG_BRANDS    = /(amazon|amazonaws|booking|airbnb|spotify|netflix|apple|google|microsoft|adobe|dropbox|zoom|asana|stripe|paypal|alibaba|aliexpress|temu|shein|wish|fnac|cdiscount|carrefour|leclerc|sncf|ratp|laposte|edf|engie|orange|sfr|free\.fr|bouygues|impots\.gouv|ameli|caf\.fr|pole.emploi|axa|allianz|maif|crédit|bnp|société.générale|hsbc)\.(com|fr|net|org)/i;

function categorize(m) {
  const from = (m.from_mail || '').toLowerCase();
  const subj = (m.subject   || '').toLowerCase();
  let score = { social: 0, notif: 0, promo: 0, main: 0 };

  // 1. Signaux serveur (RFC 2369 / 2076 / 3834) — très fiables
  if (m.list_unsub)        score.promo += 5; // List-Unsubscribe = bulk mail (newsletter, marketing)
  if (m.precedence_bulk)   score.notif += 3;
  if (m.auto_submitted)    score.notif += 4;

  // 2. Domaine expéditeur
  if (SOCIAL_DOMAINS.test(from))                                                score.social += 6;
  if (NOTIF_FROM.test(from))                                                    score.notif  += 3;
  if (PROMO_FROM.test(from))                                                    score.promo  += 2;
  if (BIG_BRANDS.test(from) && !/^(contact|hello|hi|sales|marketing)@/.test(from)) score.notif += 1;

  // 3. Sujet
  if (NOTIF_SUBJ.test(subj)) score.notif += 2;
  if (PROMO_SUBJ.test(subj)) score.promo += 3;

  // 4. Choix : max score, fallback 'main' si tout est à 0
  const cats = ['social', 'notif', 'promo'];
  let best = 'main', max = 1;
  for (const c of cats) if (score[c] >= max) { max = score[c]; best = c; }
  return best;
}
const CAT_LABELS = {
  main:   { label: 'Principale',     icon: '✉️' },
  promo:  { label: 'Promotions',     icon: '🏷️' },
  social: { label: 'Réseaux',        icon: '👥' },
  notif:  { label: 'Notifications',  icon: '🔔' },
};
// Style Gmail : icônes SVG cohérentes + couleurs de marque
const GMAIL_TABS = [
  { id: 'all',    label: 'Tous',          colorClass: 'text-[#1a73e8]',
    icon: 'M21 6h-2v9H6v2c0 .55.45 1 1 1h11l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h11c.55 0 1-.45 1-1z' },
  { id: 'main',   label: 'Principale',    colorClass: 'text-[#1a73e8]',
    icon: 'M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z' },
  { id: 'promo',  label: 'Promotions',    colorClass: 'text-[#1e8e3e]',
    icon: 'M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z' },
  { id: 'social', label: 'Réseaux',       colorClass: 'text-[#1a73e8]',
    icon: 'M12 12.75c1.63 0 3.07.39 4.24.9 1.08.48 1.76 1.56 1.76 2.73V18H6v-1.61c0-1.18.68-2.26 1.76-2.73 1.17-.52 2.61-.91 4.24-.91zM4 13c1.1 0 2-.9 2-2 0-1.1-.9-2-2-2s-2 .9-2 2c0 1.1.9 2 2 2zm1.13 1.1c-.37-.06-.74-.1-1.13-.1-.99 0-1.93.21-2.78.58C.48 14.9 0 15.62 0 16.43V18h4.5v-1.61c0-.83.23-1.61.63-2.29zM20 13c1.1 0 2-.9 2-2 0-1.1-.9-2-2-2s-2 .9-2 2c0 1.1.9 2 2 2zm4 3.43c0-.81-.48-1.53-1.22-1.85-.85-.37-1.79-.58-2.78-.58-.39 0-.76.04-1.13.1.4.68.63 1.46.63 2.29V18H24v-1.57zM12 6c1.66 0 3 1.34 3 3 0 1.66-1.34 3-3 3s-3-1.34-3-3c0-1.66 1.34-3 3-3z' },
  { id: 'notif',  label: 'Notifications', colorClass: 'text-[#f9ab00]',
    icon: 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z' },
];
const inboxTab = ref('all'); // all | main | promo | social | notif
const tabsEnabled = ref(localStorage.getItem('noliae-tabs') !== '0');
watch(tabsEnabled, (v) => localStorage.setItem('noliae-tabs', v ? '1' : '0'));

function applyUserRules(msgs, rules) {
  if (!rules?.length) return msgs;
  return msgs.map((m) => {
    const out = { ...m };
    for (const r of rules) {
      const matchFrom = r.match?.from && (
        (m.from_mail || '').toLowerCase().includes(r.match.from.toLowerCase()) ||
        (m.from || '').toLowerCase().includes(r.match.from.toLowerCase()));
      const matchSubj = r.match?.subject &&
        (m.subject || '').toLowerCase().includes(r.match.subject.toLowerCase());
      if ((r.match?.from && !matchFrom) || (r.match?.subject && !matchSubj)) continue;
      if (!r.match?.from && !r.match?.subject) continue;
      if (r.action?.color && !out.label) out.label = r.action.color;
      if (r.action?.star) out.starred = true;
    }
    return out;
  });
}

function groupConversations(msgs) {
  // Normalise le sujet (vire Re:, Fwd:, etc.) et regroupe les messages
  const norm = (s) => (s || '').replace(/^\s*(?:re|fwd?|tr)\s*:\s*/gi, '').trim().toLowerCase();
  const seen = new Map();
  for (const m of msgs) {
    const k = norm(m.subject);
    if (!seen.has(k)) seen.set(k, { ...m, conv_count: 1 });
    else { const e = seen.get(k); e.conv_count++; }
  }
  return Array.from(seen.values());
}

/* ── Dossiers ── */
const FOLDER_META = {
  inbox: { label: 'Boîte de réception', icon: 'inbox' },
  sent: { label: 'Envoyés', icon: 'sent' }, 'sent messages': { label: 'Envoyés', icon: 'sent' },
  'envoyés': { label: 'Envoyés', icon: 'sent' },
  drafts: { label: 'Brouillons', icon: 'draft' }, brouillons: { label: 'Brouillons', icon: 'draft' },
  trash: { label: 'Corbeille', icon: 'trash' }, corbeille: { label: 'Corbeille', icon: 'trash' },
  deleted: { label: 'Corbeille', icon: 'trash' },
  junk: { label: 'Spam', icon: 'spam' }, spam: { label: 'Spam', icon: 'spam' },
  archive: { label: 'Archives', icon: 'archive' }, archives: { label: 'Archives', icon: 'archive' },
};
function meta(name) {
  return FOLDER_META[(name || '').toLowerCase()] || { label: name || 'Dossier', icon: 'folder' };
}
const currentFolderName = computed(() => {
  const f = props.folders.find((x) => x.path === props.folder);
  return f ? f.name : props.folder;
});
// Dedupe : plusieurs dossiers IMAP peuvent mapper au même label "Spam"
// (Junk, Spam, INBOX.Spam, Junk E-mail…). On garde le 1er, on garde TOUJOURS
// le dossier courant + ceux qui n'ont pas de mapping (label = nom brut).
const dedupedFolders = computed(() => {
  const seen = new Set();
  return props.folders.filter((f) => {
    const m = FOLDER_META[(f.name || '').toLowerCase()];
    if (!m) return true;                 // dossier custom : tout garder
    if (f.path === props.folder) return true; // toujours montrer le courant
    if (seen.has(m.label)) return false;
    seen.add(m.label);
    return true;
  });
});
const ICONS = {
  inbox:   'M2.25 13.5h3.86a2.25 2.25 0 0 1 2.01 1.24l.26.51a2.25 2.25 0 0 0 2.01 1.25h3.22a2.25 2.25 0 0 0 2.01-1.25l.26-.51a2.25 2.25 0 0 1 2.01-1.24h3.86M3 16.06V18a2.25 2.25 0 0 0 2.25 2.25h13.5A2.25 2.25 0 0 0 21 18v-1.94m-18 0 2.76-7.18a2.25 2.25 0 0 1 2.1-1.44h8.28a2.25 2.25 0 0 1 2.1 1.44L21 16.06',
  sent:    'M6 12 3.27 3.13A59.77 59.77 0 0 1 21.49 12 59.77 59.77 0 0 1 3.27 20.88L6 12Zm0 0h7.5',
  draft:   'M19.5 14.25v-2.63a3.38 3.38 0 0 0-3.38-3.37h-1.5a1.13 1.13 0 0 1-1.12-1.13v-1.5A3.38 3.38 0 0 0 10.13 2.25H8.25M9 14.25h6m-6 3h3M10.5 2.25H5.63c-.62 0-1.13.5-1.13 1.12v17.26c0 .62.5 1.12 1.13 1.12h12.74c.62 0 1.13-.5 1.13-1.12V11.25a9 9 0 0 0-9-9Z',
  trash:   'm14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21 1.02.17M18.16 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .56 1.02-.16m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92',
  spam:    'M12 9v3.75m-9.3 3.38c-.87 1.5.22 3.37 1.95 3.37h14.7c1.73 0 2.82-1.87 1.95-3.37L13.95 3.38c-.87-1.5-3.03-1.5-3.9 0L2.7 16.13ZM12 15.75h.01v.01H12v-.01Z',
  archive: 'm20.25 7.5-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5M10 11.25h4M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.12v1.5c0 .63.5 1.13 1.13 1.13Z',
  folder:  'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.06-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.38a1.5 1.5 0 0 1-1.06-.44Z',
  // ── Actions ──
  back:        'M15.75 19.5 8.25 12l7.5-7.5',
  reply:       'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
  replyAll:    'M3.75 18 8.25 12 3.75 6M9.75 18l4.5-6-4.5-6',
  forward:     'm15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3',
  envelope:    'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.24a2.25 2.25 0 0 1-1.07 1.92l-7.5 4.61a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.92V6.75',
  check:       'm4.5 12.75 6 6 9-13.5',
  clock:       'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  list:        'M3.75 9.75h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5M3.75 5.25h16.5',
  tag:         'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z',
  print:       'M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z',
  code:        'M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5',
  info:        'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
  search:      'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
  paperclip:   'm18.38 12.79-7.24 7.24a4.5 4.5 0 0 1-6.36-6.36l8.06-8.06a3 3 0 1 1 4.24 4.24l-7.78 7.78a1.5 1.5 0 0 1-2.12-2.12l6.72-6.72',
  link:        'M13.19 8.69 8.7 13.19m1.06 5.66-1.06 1.06a4 4 0 1 1-5.66-5.66l3.54-3.54a4 4 0 0 1 5.66 0M14.24 5.15l1.06-1.06a4 4 0 1 1 5.66 5.66l-3.54 3.54a4 4 0 0 1-5.66 0',
  pencil:      'M16.86 4.49 18.55 2.8a1.88 1.88 0 1 1 2.65 2.65L10.58 16.07a4.5 4.5 0 0 1-1.9 1.13L6 18l.8-2.69a4.5 4.5 0 0 1 1.13-1.9l8.93-8.93Z',
  chevronDown: 'm19.5 8.25-7.5 7.5-7.5-7.5',
  newWindow:   'M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25',
  eye:         'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z',
  eyeOff:      'M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88',
  moon:        'M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z',
  sun:         'M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z',
  gear:        'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Zm5.406 8.06a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  keyboard:    'M7 10h.01M11 10h.01M15 10h.01M19 10h.01M7 14h10',
  shield:      'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  lock:        'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
  smile:       'M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Zm-3-11a1 1 0 1 0 0 .01zm6 0a1 1 0 1 0 0 .01zM8.5 14.5c1 1.5 2.5 2 3.5 2s2.5-.5 3.5-2',
  image:       'M3 5h18v14H3zm6 5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zM21 16l-5-5-9 8',
  sparkle:     'M12 2 14 9l7 2-7 2-2 7-2-7-7-2 7-2 2-7Z',
  hamburger:   'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
  // ── Composer toolbar ──
  alignLeft:   'M3 6h18M3 12h12M3 18h18',
  alignCenter: 'M3 6h18M6 12h12M3 18h18',
  alignRight:  'M3 6h18M9 12h12M3 18h18',
  listNumbered:'M10 6h11M10 12h11M10 18h11M4 6h1m-1 6h2m-2 6h2',
  listBullet:  'M10 6h11M10 12h11M10 18h11',
  outdent:     'M21 6H3m18 6H11m10 6H3M7 9l-3 3 3 3',
  indent:      'M3 6h18M13 12h8M3 18h18M3 9l3 3-3 3',
  clearFmt:    'M6 4h12M9 20h6M14 4l-4 16M4 8l16 12',
  quote:       'M7 7h3v6H7c0 2 1 3 3 3v2c-3 0-5-2-5-5V8a1 1 0 0 1 2-1Zm9 0h3v6h-3c0 2 1 3 3 3v2c-3 0-5-2-5-5V8a1 1 0 0 1 2-1Z',
  shieldCheck: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  spinner:     'M12 3a9 9 0 0 1 9 9',
  maximize:    'M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15',
  minimize:    'M9 9V4.5M9 9H4.5M9 9 3.75 3.75M15 9V4.5M15 9h4.5M15 9l5.25-5.25M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 15v4.5M15 15h4.5M15 15l5.25 5.25',
  warning:     'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
  shieldTLS:   'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75M3.75 21.75h16.5a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H3.75A2.25 2.25 0 0 0 1.5 10.5v9a2.25 2.25 0 0 0 2.25 2.25Z',
  dot:         'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
};
function iconPath(k) { return ICONS[k] || ICONS.folder; }

/* ── Avatars ── */
const PALETTE = ['#FF4D2E', '#0f766e', '#7c3aed', '#2563eb', '#db2777', '#ea580c', '#0891b2', '#65a30d'];
function avatarColor(s) {
  let h = 0;
  for (let i = 0; i < (s || '').length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
  return PALETTE[Math.abs(h) % PALETTE.length];
}
// Avatar self-hosted : sert l'image uploadée par l'user dans /account,
// sinon génère un SVG d'initiales déterministe (couleur basée sur le hash).
const ACCOUNT_AVATAR_BASE = '/webmail/avatar/';
/* Iframe auto-size : suit la hauteur réelle du contenu mail (style Gmail) */
function autosizeIframe(e) {
  const f = e.target;
  // Petit délai pour que les images / fonts soient chargées
  const measure = () => {
    try {
      const doc = f.contentDocument;
      if (!doc?.body) return;
      const h = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight);
      // borne haute pour éviter qu'un mail malicieux fasse exploser la page
      f.style.height = Math.min(h + 24, 5000) + 'px';
    } catch (err) {}
  };
  measure();
  // re-mesure quand toutes les images sont chargées
  setTimeout(measure, 300);
  setTimeout(measure, 1000);
  // ResizeObserver pour suivre les changements (images lazy, fonts custom)
  try {
    const ro = new ResizeObserver(measure);
    if (f.contentDocument?.body) ro.observe(f.contentDocument.body);
  } catch (err) {}
}

// ── Helpers attachments Outlook-style ───────────────────────────
const ATT_EXT_MAP = {
  pdf:  { color: 'bg-red-600',     badge: 'PDF' },
  doc:  { color: 'bg-blue-600',    badge: 'DOC' },
  docx: { color: 'bg-blue-600',    badge: 'DOC' },
  xls:  { color: 'bg-emerald-600', badge: 'XLS' },
  xlsx: { color: 'bg-emerald-600', badge: 'XLS' },
  ppt:  { color: 'bg-orange-600',  badge: 'PPT' },
  pptx: { color: 'bg-orange-600',  badge: 'PPT' },
  zip:  { color: 'bg-yellow-600',  badge: 'ZIP' },
  rar:  { color: 'bg-yellow-600',  badge: 'RAR' },
  '7z': { color: 'bg-yellow-600',  badge: '7Z'  },
  png:  { color: 'bg-purple-600',  badge: 'IMG' },
  jpg:  { color: 'bg-purple-600',  badge: 'IMG' },
  jpeg: { color: 'bg-purple-600',  badge: 'IMG' },
  gif:  { color: 'bg-purple-600',  badge: 'IMG' },
  webp: { color: 'bg-purple-600',  badge: 'IMG' },
  svg:  { color: 'bg-purple-600',  badge: 'SVG' },
  txt:  { color: 'bg-gray-600',    badge: 'TXT' },
  csv:  { color: 'bg-emerald-700', badge: 'CSV' },
  mp3:  { color: 'bg-pink-600',    badge: '♪' },
  wav:  { color: 'bg-pink-600',    badge: '♪' },
  mp4:  { color: 'bg-pink-700',    badge: '▶' },
  mov:  { color: 'bg-pink-700',    badge: '▶' },
  eml:  { color: 'bg-slate-600',   badge: '✉' },
};
function attExt(name) {
  const m = (name || '').toLowerCase().match(/\.([a-z0-9]+)$/);
  return m ? m[1] : '';
}
function attColor(name) { return (ATT_EXT_MAP[attExt(name)] || {}).color || 'bg-gray-500'; }
function attBadge(name) { return (ATT_EXT_MAP[attExt(name)] || {}).badge || '📎'; }
function attCanPreview(name) {
  return /\.(pdf|png|jpe?g|gif|webp|svg|txt)$/i.test(name || '');
}
function humanSize(b) {
  if (!b || b < 0) return '—';
  if (b < 1024) return b + ' B';
  if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
  if (b < 1024 * 1024 * 1024) return (b / 1024 / 1024).toFixed(1) + ' MB';
  return (b / 1024 / 1024 / 1024).toFixed(2) + ' GB';
}

function attachmentUrl(name, inline = false) {
  if (!props.selected) return '';
  const p = new URLSearchParams({ folder: props.folder, uid: props.selected.uid, name });
  if (inline) p.set('inline', '1');
  return '/webmail/attachment?' + p.toString();
}
function avatarUrl(hash, name) {
  if (!hash) return '';
  const u = ACCOUNT_AVATAR_BASE + hash;
  return name ? u + '?n=' + encodeURIComponent(name) : u;
}
// Logo de marque via DuckDuckGo Icon Service (rapide, gratuit, sans clé).
// Renvoie un favicon haute résolution si la marque existe (Netflix, Amazon, etc).
// 404 si domaine inconnu -> @error sur l'<img> bascule sur l'avatar par initiales.
const PERSONAL_DOMAINS = new Set([
  'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com',
  'hotmail.fr', 'outlook.com', 'outlook.fr', 'live.com', 'live.fr',
  'icloud.com', 'me.com', 'aol.com', 'free.fr', 'orange.fr', 'sfr.fr',
  'laposte.net', 'wanadoo.fr', 'proton.me', 'protonmail.com',
]);
function brandLogoUrl(email) {
  const m = String(email || '').toLowerCase().match(/@([a-z0-9.-]+\.[a-z]{2,})$/);
  if (!m) return '';
  const domain = m[1];
  // Domaines persos : pas de logo, on passe direct à l'avatar initiales.
  if (PERSONAL_DOMAINS.has(domain)) return '';
  return `https://icons.duckduckgo.com/ip3/${domain}.ico`;
}
function avatarOrLogo(hash, name, email) {
  return brandLogoUrl(email) || avatarUrl(hash, name);
}
// Allowlist curated des marques "vérifiées" : seules celles-ci ont la coche bleue.
// Match par suffixe de domaine (netflix.com, ses sous-domaines, .fr, .es, etc.)
const VERIFIED_BRANDS = [
  'netflix.', 'amazon.', 'google.', 'gmail.', 'youtube.', 'microsoft.', 'office.',
  'apple.', 'icloud.', 'github.', 'gitlab.', 'stripe.', 'paypal.', 'cloudflare.',
  'ovh.', 'hetzner.', 'scaleway.', 'aws.amazon.com', 'digitalocean.', 'linode.',
  'tf1.', 'lemonde.', 'lefigaro.', 'liberation.', 'mediapart.', 'francetv.',
  'lci.', 'bfmtv.', 'rtl.', 'europe1.', 'rmc.', 'ouest-france.', 'leparisien.',
  'sncf.', 'oui.sncf', 'ratp.', 'airfrance.', 'transavia.',
  'free.fr', 'orange.fr', 'sfr.fr', 'bouyguestelecom.', 'sosh.',
  'banquepopulaire.', 'creditmutuel.', 'caisse-epargne.', 'societegenerale.',
  'bnpparibas.', 'lcl.', 'creditagricole.', 'hellobank.', 'fortuneo.', 'boursorama.',
  'revolut.', 'n26.', 'wise.', 'lydia.', 'qonto.',
  'linkedin.', 'twitter.', 'x.com', 'facebook.', 'instagram.', 'meta.', 'whatsapp.',
  'slack.', 'discord.', 'zoom.us', 'spotify.', 'deezer.', 'twitch.',
  'fnac.', 'darty.', 'cdiscount.', 'leboncoin.', 'vinted.', 'shein.', 'zalando.',
  'airbnb.', 'booking.', 'expedia.', 'tripadvisor.',
  'uber.', 'deliveroo.', 'ubereats.', 'doctolib.',
  'edf.', 'engie.', 'totalenergies.', 'enedis.',
  'impots.gouv.fr', 'service-public.', 'ameli.', 'caf.', 'pole-emploi.', 'urssaf.',
  'noliae.',
];
function brandDomain(email) {
  const m = String(email || '').toLowerCase().match(/@([a-z0-9.-]+\.[a-z]{2,})$/);
  return m ? m[1] : '';
}
function isBrand(email) {
  const d = brandDomain(email);
  if (!d || PERSONAL_DOMAINS.has(d)) return false;
  return VERIFIED_BRANDS.some(b => d === b.replace(/\.$/, '') || d.includes(b));
}
function onLogoError(e, email) {
  e.target.src = avatarUrl(null, email) || '';
}
function initials(s) {
  if (!s) return '?';
  const parts = s.replace(/[<>"]/g, '').trim().split(/[\s.@_-]+/).filter(Boolean);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return s.replace(/[<>"]/g, '').slice(0, 2).toUpperCase();
}

/* ── Composeur ── */
const composing = ref(false);
const composeMax = ref(false);
const sending = ref(false);
const compose = ref({ to: [], cc: [], subject: '', in_reply_to: '' });
const toDraft = ref('');
const ccDraft = ref('');
const attachedFiles = ref([]);
const editorRef = ref(null);
const inlineEditorRef = ref(null);
const inlineReplyOpen = ref(false);
const inlineReplyType = ref('reply'); // 'reply' | 'forward'
const askReceipt = ref(false);

function closeInlineReply() {
  if ((inlineEditorRef.value?.innerText.trim() || '').length > 5
      && !confirm('Supprimer ce brouillon de réponse ?')) return;
  inlineReplyOpen.value = false;
  attachedFiles.value = [];
}
function expandInlineToModal() {
  // Récupère le HTML de l'éditeur inline et bascule en modal
  const html = inlineEditorRef.value?.innerHTML || '';
  inlineReplyOpen.value = false;
  composing.value = true;
  nextTick(() => { if (editorRef.value) editorRef.value.innerHTML = html; });
}
function inlineFormat(cmd) {
  if (inlineEditorRef.value) inlineEditorRef.value.focus();
  document.execCommand(cmd, false, null);
}
function inlineMakeLink() {
  const url = prompt('Adresse du lien (https://…) :');
  if (url) { inlineEditorRef.value?.focus(); document.execCommand('createLink', false, url); }
}
async function sendInlineReply() {
  // Synchronise le HTML de l'éditeur inline dans editorRef pour réutiliser sendMail
  if (!editorRef.value) {
    // Pas d'éditeur modal monté ? On en crée un temporaire en mémoire
    composing.value = true;
    await nextTick();
  }
  if (editorRef.value && inlineEditorRef.value) {
    editorRef.value.innerHTML = inlineEditorRef.value.innerHTML;
  }
  // Ferme l'inline visuellement, garde sending state
  const wasOpen = inlineReplyOpen.value;
  await sendMail();
  // Après l'envoi (qui ferme composing via onSuccess), on ferme aussi l'inline
  inlineReplyOpen.value = false;
}
const encryptBody = ref(false);
const signBody = ref(false);

/* ── Chiffrement PGP des corps de mails (inter-Noliae) ── */
async function sha256Hex(s) {
  const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(s));
  return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
}
const pgpDecryptPass = ref('');
const pgpDecryptBusy = ref(false);
const pgpDecryptError = ref('');
const pgpDecrypted = ref('');
const isPgp = computed(() => {
  const s = props.selected;
  if (!s) return false;
  const corpus = (s.text || '') + (s.html || '');
  return corpus.includes('-----BEGIN PGP MESSAGE-----');
});

/* Badges sécurité affichés dans le lecteur (style ProtonMail) */
const securityBadges = computed(() => {
  const s = props.selected;
  if (!s) return [];
  const out = [];
  const auth = s.auth || {};

  // Chiffrement E2E
  if (pgpDecrypted.value) {
    out.push({ label: 'Chiffré · déchiffré', tone: 'green', icon: 'lock',
      tooltip: 'Ce message a été chiffré de bout en bout avec OpenPGP et déchiffré dans ce navigateur uniquement.' });
  } else if (isPgp.value) {
    out.push({ label: 'Chiffré PGP', tone: 'green', icon: 'lock',
      tooltip: 'Contenu chiffré OpenPGP. Entre ta passphrase plus bas pour lire.' });
  }

  // Signature PGP
  if (auth.pgp_signed) {
    out.push({ label: 'Signé PGP', tone: 'green', icon: 'check',
      tooltip: "Le message porte une signature OpenPGP — l'auteur a prouvé son identité cryptographiquement." });
  }

  // DKIM / SPF / DMARC : un seul badge synthétique
  const dkim = auth.dkim, spf = auth.spf, dmarc = auth.dmarc;
  if (dkim || spf || dmarc) {
    const allPass = dkim === 'pass' && spf === 'pass' && dmarc === 'pass';
    const anyFail = [dkim, spf, dmarc].some(v => v === 'fail' || v === 'softfail' || v === 'permerror' || v === 'temperror');
    if (allPass) {
      out.push({ label: 'Authentifié', tone: 'green', icon: 'shield',
        tooltip: 'DKIM ✓  SPF ✓  DMARC ✓  — l\'expéditeur est cryptographiquement vérifié.' });
    } else if (anyFail) {
      out.push({ label: 'Authentification suspecte', tone: 'red', icon: 'warn',
        tooltip: `DKIM=${dkim||'?'}  SPF=${spf||'?'}  DMARC=${dmarc||'?'} — l'expéditeur n'est pas vérifié, méfie-toi.` });
    } else {
      out.push({ label: 'Auth partielle', tone: 'amber', icon: 'warn',
        tooltip: `DKIM=${dkim||'?'}  SPF=${spf||'?'}  DMARC=${dmarc||'?'}` });
    }
  }
  return out;
});
watch(() => props.selected?.uid, () => {
  pgpDecryptPass.value = ''; pgpDecryptError.value = ''; pgpDecrypted.value = '';
});
async function decryptCurrent() {
  pgpDecryptBusy.value = true; pgpDecryptError.value = '';
  try {
    const armored = (props.selected.text || props.selected.html || '');
    const m = armored.match(/-----BEGIN PGP MESSAGE-----[\s\S]+?-----END PGP MESSAGE-----/);
    if (!m) throw new Error('Bloc PGP introuvable.');

    // Déchiffrement CÔTÉ SERVEUR — la clé privée et le déchiffrement ne
    // touchent jamais le navigateur. Seul le texte clair est renvoyé.
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch('/webmail/pgp/decrypt', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
      body: JSON.stringify({ cipher: m[0], passphrase: pgpDecryptPass.value }),
    });
    const d = await r.json();
    if (!r.ok) throw new Error(d.error || 'Échec du déchiffrement.');
    pgpDecrypted.value = d.plain;
    pgpDecryptPass.value = ''; // efface la passphrase immédiatement après usage
  } catch (e) {
    pgpDecryptError.value = e.message || 'Échec — passphrase incorrecte ?';
  } finally {
    pgpDecryptBusy.value = false;
  }
}

// Récupère la clé publique d'un contact (annuaire Noliae → keyserver public)
async function fetchPublicKey(email) {
  const hash = await sha256Hex((email || '').toLowerCase().trim());
  // 1. Annuaire Noliae interne
  let r = await fetch(`/webmail/pgp/${hash}.asc`);
  if (r.ok) return await r.text();
  // 2. keys.openpgp.org via notre proxy (stocke en local pour réutilisation)
  r = await fetch(`/webmail/pgp/lookup?email=${encodeURIComponent(email)}`);
  if (r.ok) {
    const d = await r.json();
    if (d.found && d.entry?.armored) return d.entry.armored;
  }
  return null;
}

async function encryptForRecipients(plaintext, recipients) {
  const openpgp = await loadOpenpgp();
  const keys = [];
  const missing = [];
  for (const r of recipients) {
    const armored = await fetchPublicKey(r);
    if (!armored) { missing.push(r); continue; }
    keys.push(await openpgp.readKey({ armoredKey: armored }));
  }
  if (missing.length) throw new Error('Clé PGP introuvable pour : ' + missing.join(', '));
  // Joindre notre propre clé pour pouvoir relire notre copie « Envoyés »
  if (settingsForm.value.pgp_public_key) {
    keys.push(await openpgp.readKey({ armoredKey: settingsForm.value.pgp_public_key }));
  }
  return await openpgp.encrypt({
    message: await openpgp.createMessage({ text: plaintext }),
    encryptionKeys: keys,
  });
}

/* ── Paramètres webmail (signature + accusés + PGP) ── */
const settingsOpen = ref(false);
const settingsBusy = ref(false);
const settingsForm = ref({
  signature_html:    props.settings?.signature_html || '',
  ask_receipts:      !!props.settings?.ask_receipts,
  pgp_public_key:    props.settings?.pgp_public_key  || '',
  pgp_private_key:   props.settings?.pgp_private_key || '',
  pgp_fingerprint:   props.settings?.pgp_fingerprint || '',
  pgp_attach_public: !!props.settings?.pgp_attach_public,
  view_mode:         props.settings?.view_mode || 'list',
  rules:             Array.isArray(props.settings?.rules) ? JSON.parse(JSON.stringify(props.settings.rules)) : [],
});
function addRule() {
  settingsForm.value.rules.push({
    id: 'r_' + Math.random().toString(36).slice(2, 10),
    name: '',
    match: { from: '', subject: '' },
    action: { color: null, star: false },
  });
}

/* ── PGP : génération côté navigateur via OpenPGP.js (chargé à la demande) ── */
const pgpBusy = ref(false);
const pgpPass = ref('');
const pgpError = ref('');
let _openpgp = null;
async function loadOpenpgp() {
  if (_openpgp) return _openpgp;
  _openpgp = await import('https://cdn.jsdelivr.net/npm/openpgp@5.11.1/dist/openpgp.min.mjs');
  return _openpgp;
}
async function generatePgp() {
  pgpError.value = '';
  if (pgpPass.value.length < 8) { pgpError.value = 'Passphrase trop courte (8 caractères min).'; return; }
  pgpBusy.value = true;
  try {
    const openpgp = await loadOpenpgp();
    const { privateKey, publicKey } = await openpgp.generateKey({
      type: 'ecc', curve: 'curve25519',
      userIDs: [{ name: props.me_name || props.me, email: props.me }],
      passphrase: pgpPass.value,
      format: 'armored',
    });
    const k = await openpgp.readKey({ armoredKey: publicKey });
    settingsForm.value.pgp_public_key  = publicKey;
    settingsForm.value.pgp_private_key = privateKey;
    settingsForm.value.pgp_fingerprint = k.getFingerprint().toUpperCase().match(/.{1,4}/g).join(' ');
    pgpPass.value = '';
    showToast('Clé PGP générée. N\'oublie pas d\'enregistrer.', 'ok');
  } catch (e) {
    pgpError.value = e.message || 'Erreur de génération.';
  } finally {
    pgpBusy.value = false;
  }
}
function downloadPgp(which) {
  const armored = which === 'private' ? settingsForm.value.pgp_private_key : settingsForm.value.pgp_public_key;
  if (!armored) return;
  const name = which === 'private' ? 'noliae-pgp-private.asc' : 'noliae-pgp-public.asc';
  const blob = new Blob([armored], { type: 'application/pgp-keys' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = name; a.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
/* Import / lookup manuel d'une clé publique de contact */
const pgpImportEmail = ref('');
const pgpImportArmored = ref('');
const pgpImportBusy = ref(false);
const pgpImportMsg = ref(null);
async function pgpManualImport() {
  pgpImportBusy.value = true; pgpImportMsg.value = null;
  try {
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch('/webmail/pgp/import', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
      body: JSON.stringify({ email: pgpImportEmail.value, armored: pgpImportArmored.value, source: 'manual' }),
    });
    const d = await r.json();
    if (!r.ok) throw new Error(d.error || 'Erreur');
    pgpImportMsg.value = { ok: true, text: '✓ Clé importée pour ' + d.email };
    pgpImportArmored.value = '';
  } catch (e) {
    pgpImportMsg.value = { ok: false, text: e.message };
  } finally {
    pgpImportBusy.value = false;
  }
}
async function pgpKeyserverLookup() {
  pgpImportBusy.value = true; pgpImportMsg.value = null;
  try {
    const r = await fetch('/webmail/pgp/lookup?email=' + encodeURIComponent(pgpImportEmail.value));
    const d = await r.json();
    if (!r.ok || !d.found) throw new Error('Aucune clé trouvée sur keys.openpgp.org');
    pgpImportMsg.value = { ok: true, text: '✓ Trouvée et importée depuis keys.openpgp.org' };
  } catch (e) {
    pgpImportMsg.value = { ok: false, text: e.message };
  } finally {
    pgpImportBusy.value = false;
  }
}

function revokePgp() {
  if (!confirm('Révoquer la clé PGP ? Tu devras en regénérer une nouvelle pour pouvoir déchiffrer les mails reçus précédemment.')) return;
  settingsForm.value.pgp_public_key = '';
  settingsForm.value.pgp_private_key = '';
  settingsForm.value.pgp_fingerprint = '';
  settingsForm.value.pgp_attach_public = false;
}
async function saveSettings() {
  settingsBusy.value = true;
  try {
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch('/webmail/settings', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
      body: JSON.stringify(settingsForm.value),
    });
    const data = await r.json();
    if (!r.ok) throw new Error(data.error || 'Erreur');
    Object.assign(props.settings, data);
    askReceipt.value = !!data.ask_receipts;
    settingsOpen.value = false;
    showToast('Paramètres enregistrés.', 'ok');
  } catch (e) {
    showToast(e.message || 'Erreur', 'error');
  } finally {
    settingsBusy.value = false;
  }
}

/* ── Accusés de réception côté lecteur ── */
const receiptSent = ref(false);
async function sendReceipt() {
  if (!props.selected?.receipt_to || receiptSent.value) return;
  try {
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch('/webmail/receipt', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
      body: JSON.stringify({
        to: props.selected.receipt_to,
        subject: props.selected.subject,
        message_id: props.selected.message_id,
      }),
    });
    if (!r.ok) throw new Error('Erreur');
    receiptSent.value = true;
    showToast('Accusé de réception envoyé.', 'ok');
  } catch (e) {
    showToast('Échec de l\'envoi de l\'accusé.', 'error');
  }
}
watch(() => props.selected?.uid, () => { receiptSent.value = false; });

/* Signature HTML : ajout en fin de corps */
function appendSignature(html) {
  const sig = (props.settings?.signature_html || '').trim();
  if (!sig) return html;
  return (html || '') + '<br><br><div class="noliae-sig">' + sig + '</div>';
}

const EMAIL_RE = /^[^\s<>@,;"']+@[^\s<>@,;"']+\.[^\s<>@,;"']+$/;
function validEmail(s) { return EMAIL_RE.test((s || '').trim()); }
function commitChip(list, draftKey) {
  const draft = draftKey === 'toDraft' ? toDraft : ccDraft;
  const raw = (draft.value || '').trim().replace(/[,;]+$/, '');
  if (!raw) return;
  // Split sur virgules / espaces multiples si l'utilisateur colle une liste
  raw.split(/[,;\s]+/).filter(Boolean).forEach((e) => {
    if (!list.includes(e)) list.push(e);
  });
  draft.value = '';
}
function onChipKey(e, list, draftKey) {
  const draft = draftKey === 'toDraft' ? toDraft : ccDraft;
  // Entrée, Tab, virgule, point-virgule → ajoute le chip
  if (['Enter', 'Tab', ',', ';'].includes(e.key)) {
    if (draft.value.trim()) {
      e.preventDefault();
      commitChip(list, draftKey);
    }
  } else if (e.key === 'Backspace' && !draft.value && list.length) {
    // Backspace sur input vide → retire le dernier chip
    list.pop();
  }
}
function onChipPaste(e, list, draftKey) {
  const txt = (e.clipboardData || window.clipboardData).getData('text');
  if (/[,;\s]/.test(txt)) {
    e.preventDefault();
    (draftKey === 'toDraft' ? toDraft : ccDraft).value = txt;
    commitChip(list, draftKey);
  }
}

/* ── Autocomplete style O365 (To/Cc) ── */
const suggestField = ref(null);     // 'to' | 'cc' | null
const suggestItems = ref([]);
const suggestActive = ref(0);
let suggestTimer = null;
let suggestSeq = 0;

function onSuggestInput(field, value) {
  // Debounce 120ms
  if (suggestTimer) clearTimeout(suggestTimer);
  const q = (value || '').trim();
  if (q.length < 1 || q.includes('@') && q.endsWith(' ')) {
    suggestField.value = null;
    suggestItems.value = [];
    return;
  }
  suggestTimer = setTimeout(() => {
    const seq = ++suggestSeq;
    fetch('/api/contacts/suggest?q=' + encodeURIComponent(q), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    })
      .then((r) => r.ok ? r.json() : { items: [] })
      .then((j) => {
        if (seq !== suggestSeq) return; // résultats obsolètes
        suggestItems.value = j.items || [];
        suggestField.value = suggestItems.value.length ? field : null;
        suggestActive.value = 0;
      })
      .catch(() => { suggestItems.value = []; suggestField.value = null; });
  }, 120);
}

function pickSuggest(field, list, draftKey, item) {
  if (!list.includes(item.email)) list.push(item.email);
  (draftKey === 'toDraft' ? toDraft : ccDraft).value = '';
  suggestField.value = null;
  suggestItems.value = [];
  // Refocus pour enchaîner
  setTimeout(() => {
    const ref = draftKey === 'toDraft' ? 'toInput' : 'ccInput';
    document.querySelector(`input[ref="${ref}"]`)?.focus?.();
  }, 0);
}

function onSuggestKey(e, field, list, draftKey) {
  if (suggestField.value === field && suggestItems.value.length) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      suggestActive.value = (suggestActive.value + 1) % suggestItems.value.length;
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      suggestActive.value = (suggestActive.value - 1 + suggestItems.value.length) % suggestItems.value.length;
      return;
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault();
      pickSuggest(field, list, draftKey, suggestItems.value[suggestActive.value]);
      return;
    }
    if (e.key === 'Escape') {
      suggestField.value = null;
      suggestItems.value = [];
      return;
    }
  }
  // Sinon : comportement chip classique (Entrée valide adresse brute)
  onChipKey(e, list, draftKey);
}

function onSuggestBlur(field, list, draftKey) {
  // Délai pour laisser le @click du popup gagner
  setTimeout(() => {
    if (suggestField.value === field) {
      suggestField.value = null;
      suggestItems.value = [];
    }
    commitChip(list, draftKey);
  }, 150);
}

/* ── Brouillons : auto-save toutes les 5 s ── */
const draftUid = ref(null);
const draftStatus = ref('idle');   // idle | saving | saved | error
const composeDirty = ref(false);
let draftTimer = null;
let draftInFlight = false;

// Toute modif des champs ou des chips marque dirty
watch(() => [compose.value.to, compose.value.cc, compose.value.subject, compose.value.in_reply_to],
  () => { if (composing.value) composeDirty.value = true; }, { deep: true });

async function saveDraft() {
  if (!composing.value || draftInFlight) return;
  // Rien à sauvegarder si tout est vide
  const hasContent = compose.value.to.length || compose.value.cc.length
    || compose.value.subject || (editorRef.value && editorRef.value.innerText.trim());
  if (!hasContent) return;

  draftInFlight = true;
  draftStatus.value = 'saving';
  try {
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch('/webmail/draft', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
      body: JSON.stringify({
        to: compose.value.to.join(', '),
        cc: compose.value.cc.join(', '),
        subject: compose.value.subject,
        body: editorRef.value ? editorRef.value.innerHTML : '',
        in_reply_to: compose.value.in_reply_to,
        prev_uid: draftUid.value,
      }),
    });
    const data = await r.json();
    if (!r.ok) throw new Error(data.error || 'Échec');
    draftUid.value = data.uid;
    draftStatus.value = 'saved';
    composeDirty.value = false;
  } catch (e) {
    draftStatus.value = 'error';
  } finally {
    draftInFlight = false;
  }
}

// Démarre / arrête la boucle 5s au fil de l'ouverture du composeur.
watch(composing, (open) => {
  if (open) {
    composeDirty.value = false;
    draftStatus.value = 'idle';
    if (!draftTimer) draftTimer = setInterval(() => { if (composeDirty.value) saveDraft(); }, 5000);
  } else {
    if (draftTimer) { clearInterval(draftTimer); draftTimer = null; }
    draftUid.value = null;
  }
});

/* Helper : sommes-nous dans le dossier Brouillons ? */
const isDraftFolder = computed(() => {
  const f = props.folders.find((x) => x.path === props.folder);
  return f && f.rank === 1;
});

function editDraft() {
  if (!props.selected) return;
  const s = props.selected;
  // Parse To / Cc → arrays
  const splitAddr = (str) => (str || '').split(/[,;]+/).map(x => x.trim()).filter(Boolean);
  compose.value = {
    to: splitAddr(s.to),
    cc: [],
    subject: (s.subject || '').replace(/^\(sans objet\)$/i, ''),
    in_reply_to: '',
  };
  toDraft.value = ''; ccDraft.value = '';
  attachedFiles.value = [];
  draftUid.value = s.uid;
  composing.value = true;
  nextTick(() => {
    if (editorRef.value) editorRef.value.innerHTML = s.html || (s.text ? s.text.replace(/\n/g, '<br>') : '<br>');
    composeDirty.value = false;
    draftStatus.value = 'saved';
  });
}

/* ── Rédaction assistée par l'IA Noliae ── */
const aiPanelOpen = ref(false);
const aiPrompt = ref('');
const aiLoading = ref(false);
const aiError = ref('');
function openAi() { aiPanelOpen.value = true; aiError.value = ''; }
async function runAi() {
  if (!aiPrompt.value.trim() || aiLoading.value) return;
  aiLoading.value = true; aiError.value = '';
  try {
    const replyTo = editorRef.value ? editorRef.value.innerText.slice(0, 6000) : '';
    const r = await fetch('/webmail/ai/draft', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || ''),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        prompt: aiPrompt.value,
        to: Array.isArray(compose.value.to) ? compose.value.to.join(', ') : compose.value.to,
        subject: compose.value.subject,
        reply_to: replyTo,
      }),
    });
    const data = await r.json();
    if (!r.ok) throw new Error(data.error || 'Erreur IA');
    if (data.subject && !compose.value.subject) compose.value.subject = data.subject;
    else if (data.subject) compose.value.subject = data.subject;
    if (editorRef.value && data.body_html) editorRef.value.innerHTML = data.body_html;
    aiPanelOpen.value = false;
    aiPrompt.value = '';
  } catch (e) {
    aiError.value = e.message || "L'IA n'a pas pu répondre.";
  } finally {
    aiLoading.value = false;
  }
}

function setEditor(html) {
  composing.value = true;
  nextTick(() => { if (editorRef.value) editorRef.value.innerHTML = html || '<br>'; });
}
function openCompose() {
  compose.value = { to: [], cc: [], subject: '', in_reply_to: '' };
  toDraft.value = ''; ccDraft.value = '';
  attachedFiles.value = [];
  askReceipt.value = !!props.settings?.ask_receipts;
  setEditor(appendSignature('<br>'));
}
function esc(t) {
  return (t || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function origHtml(m) {
  if (!m.html) return esc(m.text || '').replace(/\n/g, '<br>');
  // Le sanitizer serveur wrappe le mail dans <!DOCTYPE><html><head>…</head><body>…</body></html>.
  // Injecter ÇA tel quel dans un contenteditable peut déclencher des comportements de parsing
  // bizarres (meta refresh, fermeture d'onglet…). On extrait juste le contenu du <body>.
  const body = m.html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
  let html = body ? body[1] : m.html;
  // On retire aussi toute balise <meta>, <link>, <base>, <script> résiduelle par sécurité.
  html = html.replace(/<(meta|link|base|script)\b[^>]*>/gi, '')
             .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
  return html;
}
function reply() {
  const m = props.selected;
  if (!m) return;
  compose.value = {
    to: m.from_mail ? [m.from_mail] : [], cc: [],
    subject: 'Re: ' + m.subject.replace(/^re\s*:\s*/i, ''),
    in_reply_to: m.message_id || '',
  };
  toDraft.value = ''; ccDraft.value = '';
  attachedFiles.value = [];
  askReceipt.value = !!props.settings?.ask_receipts;
  // Inline (style Gmail quick reply)
  inlineReplyType.value = 'reply';
  inlineReplyOpen.value = true;
  nextTick(() => {
    if (inlineEditorRef.value) {
      inlineEditorRef.value.innerHTML = appendSignature('<br>');
      inlineEditorRef.value.focus();
    }
  });
}
function forward() {
  const m = props.selected;
  if (!m) return;
  compose.value = {
    to: [], cc: [],
    subject: 'Fwd: ' + m.subject.replace(/^fwd?\s*:\s*/i, ''),
    in_reply_to: '',
  };
  toDraft.value = ''; ccDraft.value = '';
  attachedFiles.value = [];
  setEditor(appendSignature('<br><br><div style="color:#888;font-size:13px">---------- Message transféré ----------<br>'
    + 'De : ' + esc(m.from || m.from_mail) + '<br>Objet : ' + esc(m.subject) + '</div><br>'
    + origHtml(m)));
  askReceipt.value = !!props.settings?.ask_receipts;
}
/* ── Sauvegarde / restauration de la sélection ──
   Bug : quand on clique un <select> ou un <input type=color>, l'éditeur
   perd à la fois le focus ET la Selection.getRangeAt(0). On capture la
   sélection au fil de l'eau (mouseup / keyup), puis on la restore avant
   chaque exec. Sans ça, fontName/fontSize/foreColor sont des no-ops. */
let savedRange = null;
let savedEditor = null;
function activeEditor() {
  // Préfère l'éditeur principal s'il est rendu, sinon l'éditeur inline.
  return editorRef.value || inlineEditorRef.value || null;
}
function cacheSelection() {
  const sel = window.getSelection?.();
  if (!sel || sel.rangeCount === 0) return;
  const range = sel.getRangeAt(0);
  const ed = [editorRef.value, inlineEditorRef.value].find(
    (e) => e && e.contains(range.commonAncestorContainer)
  );
  if (ed) {
    savedRange = range.cloneRange();
    savedEditor = ed;
  }
}
function restoreSelection() {
  const ed = savedEditor || activeEditor();
  if (!ed) return;
  ed.focus();
  if (!savedRange) {
    // Aucune sélection mémorisée : on place le curseur à la fin
    const range = document.createRange();
    range.selectNodeContents(ed);
    range.collapse(false);
    const sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    return;
  }
  const sel = window.getSelection();
  sel.removeAllRanges();
  sel.addRange(savedRange);
}
function execWithSel(cmd, val = null) {
  restoreSelection();
  document.execCommand(cmd, false, val);
  // Après exec, la nouvelle sélection devient la référence
  cacheSelection();
}

function format(cmd, val = null) {
  // Bouton classique : @mousedown.prevent garde le focus déjà, mais on
  // passe quand même par execWithSel pour rester cohérent et marcher
  // même quand l'éditeur a perdu le focus (clic dans un select avant).
  execWithSel(cmd, val);
}
function makeLink() {
  const url = prompt('Adresse du lien (https://…) :');
  if (url) execWithSel('createLink', url);
}

/* ── Police, taille, couleur, émojis, image, signature ── */
const textColor = ref('#FF4D2E');
const emojiOpen = ref(false);
function setFont(name) {
  if (!name) return;
  execWithSel('fontName', name);
}
function setSize(s) {
  if (!s) return;
  execWithSel('fontSize', s);
}
function applyColor() {
  execWithSel('foreColor', textColor.value);
}
function insertText(t) {
  execWithSel('insertText', t);
}
/* ── Images inline : upload S3 puis insertion dans le corps ── */
const imgUploading = ref(false);

async function uploadInline(file) {
  if (!file || !file.type.startsWith('image/')) return null;
  if (file.size > 10 * 1024 * 1024) {
    alert('Image trop volumineuse (10 Mo max) : ' + (file.name || ''));
    return null;
  }
  const fd = new FormData();
  fd.append('image', file, file.name || 'image.png');
  const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch('/webmail/inline-image', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
    body: fd,
  });
  const data = await r.json();
  if (!r.ok) {
    const msg = data.errors?.image?.[0] || data.error || data.message || "Échec de l'upload";
    throw new Error(msg);
  }
  return data.url;
}

async function onPickImages(files) {
  if (!files || !files.length) return;
  imgUploading.value = true;
  try {
    if (editorRef.value) editorRef.value.focus();
    for (const f of files) {
      const url = await uploadInline(f);
      if (url) {
        document.execCommand('insertHTML', false,
          `<img src="${url.replace(/"/g, '&quot;')}" style="max-width:100%;height:auto;border-radius:8px;margin:6px 0" alt="">`);
      }
    }
  } catch (e) {
    showToast(e.message || "Image refusée.", 'error');
  } finally {
    imgUploading.value = false;
  }
}

async function onEditorPaste(e) {
  const items = e.clipboardData ? Array.from(e.clipboardData.items) : [];
  const files = items.filter(i => i.kind === 'file' && i.type.startsWith('image/')).map(i => i.getAsFile());
  if (!files.length) return;
  e.preventDefault();
  onPickImages(files);
}
async function onEditorDrop(e) {
  const files = e.dataTransfer ? Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')) : [];
  if (!files.length) return;
  onPickImages(files);
}
function insertSignature() {
  const me = props.me || '';
  const sig = `<br><br>—<br><strong>${(usePage().props.auth?.user?.name) || me}</strong><br>` +
              `<span style="color:#9ca3af;font-size:12px">${me}</span>`;
  if (editorRef.value) {
    editorRef.value.focus();
    document.execCommand('insertHTML', false, sig);
  }
}
function addFiles(e) {
  const MAX = 1024 * 1024 * 1024; // 1 Go max par fichier (cap S3)
  const tooBig = [];
  let added = 0;
  for (const f of e.target.files) {
    if (f.size > MAX) { tooBig.push(f.name); continue; }
    attachedFiles.value.push(f);
    added++;
  }
  e.target.value = '';
  if (tooBig.length) {
    alert('Fichier' + (tooBig.length > 1 ? 's' : '') + ' au-dessus de 1 Go :\n• ' + tooBig.join('\n• '));
  }
  // Si le total dépasse 10 Mo, on bascule auto en mode stockage Noliae.
  if (added && totalAttachSize.value > 10 * 1024 * 1024 && storageMode.value === 'smtp') {
    storageMode.value = 's3';
  }
}

/* ── Stockage des pièces jointes ── */
const storageMode = ref('smtp');   // 'smtp' = 10 Mo direct ; 's3' = 1 Go
const ttlDays = ref(7);
const totalAttachSize = computed(() => attachedFiles.value.reduce((s, f) => s + (f.size || 0), 0));
const overSmtp = computed(() => totalAttachSize.value > 10 * 1024 * 1024);
const overLimit = computed(() => storageMode.value === 's3'
  ? totalAttachSize.value > 1024 * 1024 * 1024
  : overSmtp.value);
const sizePct = computed(() => {
  const cap = storageMode.value === 's3' ? 1024 * 1024 * 1024 : 10 * 1024 * 1024;
  return Math.round((totalAttachSize.value / cap) * 100);
});
function fmtSize(b) {
  if (b < 1024) return b + ' o';
  if (b < 1048576) return Math.round(b / 1024) + ' Ko';
  return (b / 1048576).toFixed(1) + ' Mo';
}
const uploadPct = ref(0);

// Nettoie le HTML produit par contenteditable :
//   - remplace tous les &nbsp; / U+00A0 par un espace régulier
//   - collapse les espaces multiples en un seul (hors <pre>)
function normalizeBody(html) {
  return (html || '')
    .replace(/(&nbsp;| |&#160;|&#xA0;)+/gi, ' ')
    .replace(/[ \t]{2,}/g, ' ')
    // contenteditable insère <p><br></p> quand on tape Entrée sur une ligne vide
    .replace(/<p>\s*(?:<br\s*\/?>\s*)*<\/p>/gi, '')
    // collapse >2 <br> consécutifs en 2
    .replace(/(?:<br\s*\/?>\s*){3,}/gi, '<br><br>');
}

// File « envoi différé client-side » : 30 s pour annuler
const pendingSend = ref(null); // { payload, timeoutId, endsAt }
function actuallySend(payload) {
  // Si on est en mode "vue boîte partagée", on transmet l'ID pour que le
  // backend signe l'envoi avec l'identité shared (after ACL check).
  const url = asSharedId.value ? `/webmail/send?as=${asSharedId.value}` : '/webmail/send';
  router.post(url, payload, {
    forceFormData: true, preserveScroll: true,
    onProgress: (e) => { if (e?.percentage !== undefined) uploadPct.value = e.percentage; },
    onSuccess: () => { composing.value = false; uploadPct.value = 0; draftUid.value = null; },
    onFinish:  () => { sending.value = false; pendingSend.value = null; },
  });
}
/** Annule la fenêtre d'attente undo-send et expédie immédiatement. */
function sendPendingNow() {
  if (!pendingSend.value) return;
  clearTimeout(pendingSend.value.timeoutId);
  const payload = pendingSend.value.payload;
  pendingSend.value = null;
  actuallySend(payload);
}
function cancelPendingSend() {
  if (!pendingSend.value) return;
  clearTimeout(pendingSend.value.timeoutId);
  pendingSend.value = null;
  sending.value = false;
  showToast('Envoi annulé.', 'ok');
}

async function sendMail() {
  commitChip(compose.value.to, 'toDraft');
  commitChip(compose.value.cc, 'ccDraft');
  let body = normalizeBody(editorRef.value ? editorRef.value.innerHTML : '');

  // Signature PGP optionnelle (server-side via gpg)
  if (signBody.value && !encryptBody.value) {
    const passphrase = prompt('Passphrase de ta clé PGP pour signer ce message :');
    if (passphrase) {
      try {
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const r = await fetch('/webmail/pgp/sign', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
          body: JSON.stringify({ text: (editorRef.value?.innerText || '').trim(), passphrase }),
        });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || 'Signature impossible');
        body = '<pre style="font-family:monospace;white-space:pre-wrap">' + d.signed.replace(/</g, '&lt;') + '</pre>';
      } catch (e) {
        showToast(e.message, 'error');
        return;
      }
    }
  }

  // Chiffrement PGP optionnel
  if (encryptBody.value) {
    try {
      sending.value = true;
      const all = [...compose.value.to, ...compose.value.cc];
      // Texte brut pour PGP (le HTML perd le formatage mais c'est la trade-off du chiffré)
      const text = (editorRef.value?.innerText || '').trim() || ' ';
      body = await encryptForRecipients(text, all);
    } catch (e) {
      sending.value = false;
      showToast(e.message || 'Chiffrement impossible', 'error');
      return;
    }
  }

  sending.value = true;
  uploadPct.value = 0;
  const payload = {
    to: compose.value.to.join(', '),
    cc: compose.value.cc.join(', '),
    subject: compose.value.subject,
    in_reply_to: compose.value.in_reply_to,
    body,
    pgp_encrypted: encryptBody.value,
    attachments: attachedFiles.value,
    storage: storageMode.value,
    ttl_days: ttlDays.value,
    ask_receipt: askReceipt.value,
    draft_uid: draftUid.value,
    send_at: scheduleAt.value || null,
  };

  // Si l'utilisateur a planifié → on saute le délai d'annulation
  if (scheduleAt.value) { actuallySend(payload); return; }

  // Sinon : fenêtre d'annulation 30 s
  composing.value = false; // ferme la modale pour libérer l'écran
  pendingSend.value = { payload, endsAt: Date.now() + 30000 };
  pendingSend.value.timeoutId = setTimeout(() => {
    if (pendingSend.value) actuallySend(pendingSend.value.payload);
  }, 30000);
}

/* ── Toast ── */
const toast = ref(null);
const flash = computed(() => usePage().props.flash || {});
watch(flash, (f) => {
  if (f.success) showToast(f.success, 'ok');
  else if (f.error) showToast(f.error, 'error');
}, { immediate: true, deep: true });
function showToast(msg, type, undo = null) {
  toast.value = { msg, type, undo };
  setTimeout(() => { toast.value = null; }, undo ? 8000 : 4500);
}

const drawerOpen = ref(false);
const darkMode = ref(localStorage.getItem('noliae-dark') === '1');
watch(darkMode, (v) => localStorage.setItem('noliae-dark', v ? '1' : '0'));

/* ── Raccourcis : modale d'aide ── */
const shortcutsOpen = ref(false);
const senderInfoOpen = ref(false);
const senderDetailsExpanded = ref(false);
const isMeRecipient = computed(() => {
  if (!props.selected?.to || !props.me) return false;
  return props.selected.to.toLowerCase().includes(props.me.toLowerCase());
});
watch(() => props.selected?.uid, () => { senderDetailsExpanded.value = false; });
function showRaw() {
  if (!props.selected) return;
  const url = '/webmail/raw?folder=' + encodeURIComponent(props.folder) + '&uid=' + props.selected.uid;
  window.open(url, '_blank', 'width=1100,height=800');
}
function printMail() {
  const w = window.open('', '_blank');
  if (!w) return;
  const html = props.selected?.html
    || `<pre style="font:14px ui-monospace,monospace;white-space:pre-wrap">${(props.selected?.text || '').replace(/</g, '&lt;')}</pre>`;
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${(props.selected?.subject || '').replace(/</g,'&lt;')}</title>
<style>body{font:14px -apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:700px;margin:30px auto;padding:0 20px}h1{font-size:18px}.meta{color:#888;font-size:12px;margin-bottom:24px;border-bottom:1px solid #eee;padding-bottom:12px}</style>
</head><body><h1>${(props.selected?.subject || '').replace(/</g,'&lt;')}</h1><div class="meta">${(props.selected?.from || '').replace(/</g,'&lt;')} · ${fmtFull(props.selected?.date)}</div>${html}</body></html>`);
  w.document.close(); w.focus(); setTimeout(() => w.print(), 250);
}
const shortcutGroups = [
  {
    title: 'Navigation',
    items: [
      { label: 'Message suivant',   keys: ['j'] },
      { label: 'Message précédent', keys: ['k'] },
      { label: 'Retour à la liste', keys: ['Esc'] },
      { label: 'Focus recherche',   keys: ['/'] },
    ],
  },
  {
    title: 'Actions sur un message',
    items: [
      { label: 'Répondre',          keys: ['r'] },
      { label: 'Transférer',        keys: ['f'] },
      { label: 'Archiver',          keys: ['e'] },
      { label: 'Supprimer',         keys: ['#'] },
      { label: 'Favori (étoile)',   keys: ['s'] },
      { label: 'Marquer non lu',    keys: ['u'] },
    ],
  },
  {
    title: 'Composition',
    items: [
      { label: 'Nouveau message',         keys: ['c'] },
      { label: 'Envoyer (dans le composeur)', keys: ['⌘/Ctrl', '⏎'] },
    ],
  },
  {
    title: 'Sécurité & confort',
    items: [
      { label: 'Mode confidentialité',    keys: ['⌘/Ctrl', '⇧', 'H'] },
      { label: 'Aide raccourcis (cet écran)', keys: ['?'] },
    ],
  },
];

/* ── Snooze ── */
const snoozeOpen = ref(false);
const snoozeOptions = computed(() => {
  const d = new Date();
  const fmt = (dt) => dt.toLocaleString('fr-FR', { weekday: 'short', hour: '2-digit', minute: '2-digit' });
  return [
    { label: 'Dans 1 heure',  hours: 1,   preview: fmt(new Date(d.getTime() + 3600e3)) },
    { label: 'Ce soir 18 h',  hours: -1,  preview: '18:00', special: 'tonight' },
    { label: 'Demain 9 h',    hours: -1,  preview: 'demain 09:00', special: 'tomorrow' },
    { label: 'Lundi prochain', hours: -1, preview: 'lun. 09:00', special: 'monday' },
    { label: 'Dans 1 semaine', hours: 24*7, preview: fmt(new Date(d.getTime() + 7*24*3600e3)) },
  ];
});
function snoozeNow(hours) {
  if (!props.selected) return;
  const d = new Date();
  let until;
  if (hours > 0) until = new Date(d.getTime() + hours * 3600e3);
  else { // special tokens — use simple defaults
    until = new Date(d); until.setHours(18, 0, 0, 0); if (d.getHours() >= 18) until.setDate(d.getDate() + 1);
  }
  router.post('/webmail/snooze', { folder: props.folder, uid: props.selected.uid, until: until.toISOString() },
    { preserveScroll: true });
  snoozeOpen.value = false;
}

/* ── Schedule send ── */
const scheduleOpen = ref(false);
const scheduleAt = ref(''); // datetime-local string
const scheduleOptions = computed(() => {
  const d = new Date();
  const fmt = (dt) => dt.toLocaleString('fr-FR', { weekday: 'short', hour: '2-digit', minute: '2-digit' });
  return [
    { label: 'Dans 1 heure',   hours: 1,   preview: fmt(new Date(d.getTime() + 3600e3)) },
    { label: 'Demain matin 8 h', hours: -1, preview: 'demain 08:00', special: 'tomorrow8' },
    { label: 'Lundi 9 h',      hours: -1,  preview: 'lun. 09:00', special: 'monday9' },
  ];
});
function pickSchedule(hours) {
  const d = new Date();
  let dt;
  if (hours > 0) dt = new Date(d.getTime() + hours * 3600e3);
  else { dt = new Date(d); dt.setHours(8, 0, 0, 0); dt.setDate(dt.getDate() + 1); }
  // ISO local pour input datetime-local : YYYY-MM-DDTHH:MM
  scheduleAt.value = dt.toISOString().slice(0, 16);
  scheduleOpen.value = false;
}

/* ── Smart Reply IA ── */
const smartReplies = ref([]);
const smartLoading = ref(false);
async function fetchSmartReplies() {
  smartReplies.value = []; smartLoading.value = false;
  if (!props.enable_noliae_ai) return;
  if (!props.selected || isPgp.value) return;
  const text = props.selected.text || (props.selected.html || '').replace(/<[^>]+>/g, ' ').slice(0, 4000);
  if (!text || text.length < 20) return;
  smartLoading.value = true;
  try {
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const r = await fetch('/webmail/ai/smart-reply', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
      body: JSON.stringify({ text, subject: props.selected.subject, from: props.selected.from_mail }),
    });
    const d = await r.json();
    if (r.ok && Array.isArray(d.replies)) smartReplies.value = d.replies.slice(0, 3);
  } catch (e) {}
  finally { smartLoading.value = false; }
}
function useSmartReply(text) {
  reply();
  nextTick(() => {
    if (editorRef.value) {
      editorRef.value.innerHTML = `<p>${text.replace(/</g, '&lt;')}</p>` + editorRef.value.innerHTML;
    }
  });
}
watch(() => props.selected?.uid, () => {
  fetchSmartReplies();
  aiTranslated.value = ''; aiSummary.value = '';
  phishingRisk.value = ''; phishingReason.value = '';
  scanPhishing();
});

/* ── Traduction + Résumé + Phishing IA ── */
const aiBusy = ref({ translate: false, summarize: false });
const aiTranslated = ref('');
const aiSummary = ref('');
const phishingRisk = ref('');
const phishingReason = ref('');

async function callAi(path, body) {
  const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch(path, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf },
    body: JSON.stringify(body),
  });
  const d = await r.json();
  if (!r.ok) throw new Error(d.error || 'Erreur IA');
  return d;
}
function plainBody() {
  return (props.selected?.text || (props.selected?.html || '').replace(/<[^>]+>/g, ' ')).slice(0, 8000).trim();
}
async function aiTranslate() {
  if (aiTranslated.value) { aiTranslated.value = ''; return; }
  aiBusy.value.translate = true;
  try {
    const d = await callAi('/webmail/ai/translate', { text: plainBody(), to: 'fr' });
    aiTranslated.value = d.translated;
    aiSummary.value = ''; // mutually exclusive overlay
  } catch (e) { showToast(e.message, 'error'); }
  finally { aiBusy.value.translate = false; }
}
async function aiSummarize() {
  if (aiSummary.value) { aiSummary.value = ''; return; }
  aiBusy.value.summarize = true;
  try {
    const d = await callAi('/webmail/ai/summarize', { text: plainBody() });
    aiSummary.value = d.summary;
    aiTranslated.value = '';
  } catch (e) { showToast(e.message, 'error'); }
  finally { aiBusy.value.summarize = false; }
}
async function scanPhishing() {
  if (!props.selected || isPgp.value) return;
  try {
    const d = await callAi('/webmail/ai/phishing', {
      from: props.selected.from_mail || '',
      subject: props.selected.subject || '',
      text: plainBody(),
    });
    phishingRisk.value = d.risk || 'low';
    phishingReason.value = d.reason || '';
  } catch (e) { /* silent */ }
}

/* ── Countdown Undo Send ── */
const undoCountdown = ref(30);
let undoInterval = null;
watch(pendingSend, (v) => {
  if (undoInterval) { clearInterval(undoInterval); undoInterval = null; }
  if (!v) return;
  undoCountdown.value = 30;
  undoInterval = setInterval(() => {
    const left = Math.max(0, Math.round((v.endsAt - Date.now()) / 1000));
    undoCountdown.value = left;
    if (left <= 0) { clearInterval(undoInterval); undoInterval = null; }
  }, 1000);
});

/* ── Raccourcis clavier (style Gmail) ── */
function onShortcuts(e) {
  if (e.target.matches('input, textarea, [contenteditable]')) return;
  if (e.metaKey || e.ctrlKey || e.altKey) return;
  switch (e.key.toLowerCase()) {
    case 'c': openCompose(); break;
    case '/':
      e.preventDefault();
      document.querySelector('input[placeholder*="Rechercher"]')?.focus();
      break;
    case 'j': navMessage(+1); break;
    case 'k': navMessage(-1); break;
    case 'r': if (props.selected) reply(); break;
    case 'f': if (props.selected) forward(); break;
    case 'e': if (props.selected) doArchive(); break;
    case '#': if (props.selected) doDelete(); break;
    case 's': if (props.selected) toggleStar(props.selected); break;
    case 'u': if (props.selected) toggleSeen(); break;
    case 'escape':
      if (shortcutsOpen.value) { shortcutsOpen.value = false; break; }
      if (props.selected) backToList(); break;
    case '?': e.preventDefault(); shortcutsOpen.value = true; break;
  }
}
function navMessage(direction) {
  const list = displayedMessages.value;
  if (!list.length) return;
  const idx = props.selected ? list.findIndex(m => m.uid === props.selected.uid) : -1;
  const next = idx === -1 ? 0 : Math.min(list.length - 1, Math.max(0, idx + direction));
  openMessage(list[next].uid);
}

/* ── Mode confidentialité (anti-screenshare / shoulder surfing) ── */
const privacyMode = ref(false);
function togglePrivacy() { privacyMode.value = !privacyMode.value; }

// Auto-trigger DÉSACTIVÉ — trop intrusif au quotidien (ça flouait à chaque
// switch d'onglet). Le mode reste accessible manuellement : bouton œil
// dans le header ou raccourci ⌘/Ctrl+Shift+H.
function onVisibility() { /* no-op */ }
function onWindowBlur()  { /* no-op */ }
function onKey(e) {
  // Cmd/Ctrl + Shift + H (hide)
  if ((e.metaKey || e.ctrlKey) && e.shiftKey && (e.key === 'H' || e.key === 'h')) {
    e.preventDefault(); togglePrivacy();
  }
}
onMounted(() => {
  document.addEventListener('visibilitychange', onVisibility);
  window.addEventListener('blur', onWindowBlur);
  window.addEventListener('keydown', onKey);
  window.addEventListener('keydown', onShortcuts);
});
onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibility);
  window.removeEventListener('blur', onWindowBlur);
  window.removeEventListener('keydown', onKey);
  window.removeEventListener('keydown', onShortcuts);
});
const searchInput = ref(props.search || '');
let searchDebounce = null;
function runSearch() {
  router.get('/webmail', { folder: props.folder, q: searchInput.value.trim() || undefined },
    { preserveState: false });
}
// debounce live search after 600 ms
watch(searchInput, (v) => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => { if ((v || '') !== (props.search || '')) runSearch(); }, 600);
});
function openFolder(path) {
  moveOpen.value = false; drawerOpen.value = false;
  router.get('/webmail', { folder: path }, { preserveState: false });
}
// ── Pagination ──
const totalPages = computed(() => Math.max(1, Math.ceil((props.total || 0) / (props.per_page || 40))));
const pageRangeLabel = computed(() => {
  const t = props.total || 0;
  if (!t) return '';
  const start = (props.page - 1) * props.per_page + 1;
  const end = Math.min(t, start + props.messages.length - 1);
  return `${start}–${end} sur ${t}`;
});
function gotoPage(p) {
  // Si total inconnu (countMessages a échoué) on n'applique pas le cap haut.
  const cap = props.total > 0 ? totalPages.value : 99999;
  const next = Math.min(cap, Math.max(1, p));
  if (next === props.page) return;
  router.get('/webmail', {
    folder: props.folder,
    q: props.search || undefined,
    page: next,
    per_page: props.per_page !== 40 ? props.per_page : undefined,
  }, { preserveState: false, preserveScroll: false });
}
function openMessage(uid) {
  moveOpen.value = false; drawerOpen.value = false;
  router.get('/webmail', {
    folder: props.folder, uid,
    page: props.page > 1 ? props.page : undefined,
    per_page: props.per_page !== 40 ? props.per_page : undefined,
    q: props.search || undefined,
  }, { preserveScroll: true });
}
function backToList() {
  router.get('/webmail', {
    folder: props.folder,
    page: props.page > 1 ? props.page : undefined,
    per_page: props.per_page !== 40 ? props.per_page : undefined,
    q: props.search || undefined,
  }, { preserveScroll: true });
}

/* ── Gestion des dossiers ── */
const moveOpen = ref(false);
function newFolder() {
  const n = prompt('Nom du nouveau dossier :');
  if (n && n.trim()) router.post('/webmail/folders', { name: n.trim() }, { preserveScroll: true });
}
function delFolder(f) {
  if (confirm('Supprimer le dossier « ' + meta(f.name).label + ' » ? Son contenu sera perdu.'))
    router.delete('/webmail/folders', { data: { path: f.path } });
}
function doMove(target) {
  moveOpen.value = false;
  if (!props.selected) return;
  router.post('/webmail/move', { folder: props.folder, uid: props.selected.uid, target },
    { preserveScroll: true });
}
function doArchive() {
  if (!props.selected) return;
  router.post('/webmail/archive', { folder: props.folder, uid: props.selected.uid });
}
function doSpam() {
  if (!props.selected) return;
  router.post('/webmail/spam', { folder: props.folder, uid: props.selected.uid });
}
function doDelete() {
  if (!props.selected) return;
  const isTrash = meta(currentFolderName.value).icon === 'trash';
  if (isTrash && !confirm('Supprimer définitivement ce message ?')) return;
  router.post('/webmail/trash', { folder: props.folder, uid: props.selected.uid });
}
function toggleSeen() {
  if (!props.selected) return;
  router.post('/webmail/seen',
    { folder: props.folder, uid: props.selected.uid, seen: false },
    { preserveScroll: true });
}

/* ── Sélection multiple ── */
const selectedUids = ref([]);
const bulkMoveOpen = ref(false);
const allSelected = computed(() =>
  props.messages.length > 0 && selectedUids.value.length === props.messages.length);
function isSel(uid) { return selectedUids.value.includes(uid); }
function toggleSel(uid) {
  const i = selectedUids.value.indexOf(uid);
  if (i >= 0) selectedUids.value.splice(i, 1);
  else selectedUids.value.push(uid);
}
function toggleAll() {
  selectedUids.value = allSelected.value ? [] : props.messages.map((m) => m.uid);
}
function clearSel() { selectedUids.value = []; bulkMoveOpen.value = false; }
/* ── Menu contextuel clic-droit ── */
const ctx = ref(null); // { x, y, m }
function openContext(ev, m) {
  // Si le mail n'est pas encore ouvert, on le sélectionne pour les actions
  if (!props.selected || props.selected.uid !== m.uid) openMessage(m.uid);
  // Position en évitant le débordement
  const x = Math.min(ev.clientX, window.innerWidth - 280);
  const y = Math.min(ev.clientY, window.innerHeight - 460);
  ctx.value = { x, y, m };
}
function closeContext() { ctx.value = null; }
onMounted(() => {
  document.addEventListener('click', closeContext);
  document.addEventListener('scroll', closeContext, true);
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeContext(); });
});

const ctxItems = computed(() => {
  if (!ctx.value) return [];
  const m = ctx.value.m;
  const ic = (svgPath) => `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="${svgPath}"/></svg>`;
  return [
    { label: 'Répondre',           icon: ic('M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3'),
      right: 'R', action: () => { openMessage(m.uid); nextTick(reply); } },
    { label: 'Répondre à tous',    icon: ic('M3.75 18 8.25 12 3.75 6M9.75 18l4.5-6-4.5-6'),
      right: 'A', action: () => { openMessage(m.uid); nextTick(replyAll); } },
    { label: 'Transférer',         icon: ic('m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3'),
      right: 'F', action: () => { openMessage(m.uid); nextTick(forward); } },
    { sep: true, label: '─', icon: '', action: () => {} },
    { label: 'Archiver',           icon: ic('m20.25 7.5-.63 10.63a2.25 2.25 0 0 1-2.24 2.12H6.62a2.25 2.25 0 0 1-2.24-2.12L3.75 7.5M10 11.25h4M3.38 7.5h17.25c.62 0 1.12-.5 1.12-1.13v-1.5c0-.62-.5-1.12-1.12-1.12H3.38c-.63 0-1.13.5-1.13 1.12v1.5c0 .63.5 1.13 1.13 1.13Z'),
      right: 'E', action: () => quickArchive(m) },
    { label: 'Supprimer',          tone: 'danger',
      icon: ic('m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21 1.02.17M18.16 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79'),
      right: '#', action: () => quickTrash(m) },
    { label: m.seen ? 'Marquer non lu' : 'Marquer lu',
      icon: ic('M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75'),
      right: 'U', action: () => quickSeen(m) },
    { label: m.starred ? 'Retirer favori' : 'Ajouter aux favoris',
      icon: ic('M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z'),
      right: 'S', action: () => toggleStar(m) },
    { label: 'Mettre en attente (snooze)',
      icon: ic('M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'),
      action: () => { openMessage(m.uid); nextTick(() => snoozeOpen.value = true); } },
    { sep: true, label: '─', icon: '', action: () => {} },
    { label: 'Déplacer vers…',
      icon: ic('M3.75 9.75h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5M3.75 5.25h16.5'),
      action: () => { openMessage(m.uid); nextTick(() => moveOpen.value = true); } },
    { label: 'Ajouter un libellé…',
      icon: ic('M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z'),
      action: () => cycleLabel(m) },
    { label: 'Rechercher mails de ' + (m.from_mail || m.from),
      icon: ic('m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z'),
      action: () => { searchInput.value = 'from:' + (m.from_mail || m.from); runSearch(); } },
    { label: 'Ouvrir dans une nouvelle fenêtre',
      icon: ic('M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25'),
      action: () => window.open('/webmail?folder=' + encodeURIComponent(props.folder) + '&uid=' + m.uid, '_blank') },
  ].filter((i) => !i.sep);
});
function replyAll() { reply(); /* Note : tous les destinataires ne sont pas connus côté client. Reply pour l'instant */ }

function quickArchive(m) {
  router.post('/webmail/archive', { folder: props.folder, uid: m.uid }, { preserveScroll: true });
}
function quickTrash(m) {
  router.post('/webmail/trash', { folder: props.folder, uid: m.uid }, { preserveScroll: true });
}
function quickSeen(m) {
  router.post('/webmail/seen', { folder: props.folder, uid: m.uid, seen: !m.seen }, { preserveScroll: true });
}
function toggleStar(m) {
  m.starred = !m.starred; // optimistic
  router.post('/webmail/star', { folder: props.folder, uid: m.uid, on: m.starred },
    { preserveScroll: true, preserveState: true });
}
function cycleLabel(m) {
  // Rotation : null → red → orange → … → violet → null
  const i = m.label ? LABEL_LIST.indexOf(m.label) : -1;
  const next = i >= LABEL_LIST.length - 1 ? null : LABEL_LIST[i + 1];
  m.label = next;
  router.post('/webmail/label', { folder: props.folder, uid: m.uid, color: next },
    { preserveScroll: true, preserveState: true });
}
const displayedMessages = computed(() => {
  let list = applyUserRules(props.messages, props.settings?.rules || []);
  if (currentFolderName.value && /inbox/i.test(currentFolderName.value) && tabsEnabled.value) {
    list = list.map((m) => ({ ...m, _cat: categorize(m) }));
    if (inboxTab.value !== 'all') list = list.filter((m) => m._cat === inboxTab.value);
  }
  if ((props.settings?.view_mode) === 'thread') list = groupConversations(list);
  return list;
});
const tabCounts = computed(() => {
  const c = { all: 0, main: 0, promo: 0, social: 0, notif: 0 };
  for (const m of props.messages) { c.all++; c[categorize(m)]++; }
  return c;
});

function bulk(action, target) {
  if (!selectedUids.value.length) return;
  router.post('/webmail/bulk', {
    folder: props.folder,
    uids: selectedUids.value,
    action,
    target: target || null,
  }, { preserveScroll: true, onSuccess: () => clearSel() });
}
watch(() => props.folder, () => clearSel());

/* ── Indicateur de stockage (anneau animé) ── */
const RING_C = 2 * Math.PI * 20;
const quotaPct = ref(0);
const quotaUsedAnim = ref(0);
const quotaOffset = computed(() => RING_C * (1 - quotaPct.value / 100));
const quotaColor = computed(() => {
  const p = props.quota ? props.quota.percent : 0;
  if (p >= 90) return '#e11d48';
  if (p >= 70) return '#f59e0b';
  return '#FF4D2E';
});
function animateQuota() {
  if (!props.quota) return;
  const tPct = props.quota.percent || 0;
  const tUsed = props.quota.used || 0;
  const t0 = performance.now(), dur = 1100;
  function step(now) {
    const t = Math.min(1, (now - t0) / dur);
    const e = 1 - Math.pow(1 - t, 3); // easeOutCubic
    quotaPct.value = tPct * e;
    quotaUsedAnim.value = tUsed * e;
    if (t < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
function fmtBytes(b) {
  b = Number(b) || 0;
  const u = ['o', 'Ko', 'Mo', 'Go', 'To'];
  let i = 0;
  while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
  return (b < 10 && i > 0 ? b.toFixed(1) : Math.round(b)) + ' ' + u[i];
}

/* ── Mode live : rafraîchissement automatique de la liste ── */
const polling = ref(true);
let pollTimer = null;
onMounted(() => {
  animateQuota();
  pollTimer = setInterval(() => {
    // On ne rafraîchit pas si l'onglet est masqué ou si on est en train d'écrire.
    if (document.visibilityState !== 'visible' || composing.value) return;
    router.reload({ only: ['messages'], preserveState: true, preserveScroll: true });
  }, 15000);
});
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); });

function fmtDate(d) {
  if (!d) return '';
  const dt = new Date(d), now = new Date();
  if (dt.toDateString() === now.toDateString())
    return dt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  if (dt.getFullYear() === now.getFullYear())
    return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
  return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}
function fmtFull(d) {
  if (!d) return '';
  return new Date(d).toLocaleDateString('fr-FR',
    { weekday: 'short', day: '2-digit', month: 'long', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all .25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px); }
[contenteditable]:empty:before { content: 'Votre message…'; color: #9ca3af; }

/* Restaure les styles de liste détruits par Tailwind preflight (dans l'éditeur ET dans la signature affichée) */
[contenteditable] ul, .noliae-sig ul { list-style: disc;    padding-left: 1.5rem; margin: .5rem 0; }
[contenteditable] ol, .noliae-sig ol { list-style: decimal; padding-left: 1.5rem; margin: .5rem 0; }
[contenteditable] li, .noliae-sig li { margin: .2rem 0; }
[contenteditable] blockquote { border-left: 3px solid #e5e7eb; padding-left: 12px; color: #6b7280; margin: 8px 0; }
[contenteditable] a { color: #FF4D2E; text-decoration: underline; }
[contenteditable] p { margin: .25rem 0; }
[contenteditable] h1, [contenteditable] h2, [contenteditable] h3 { font-weight: 800; margin: .5rem 0; }
[contenteditable] h1 { font-size: 1.5rem; }
[contenteditable] h2 { font-size: 1.25rem; }

/* Indicateur de stockage */
.quota-enter-active { transition: all .55s cubic-bezier(.16, 1, .3, 1); }
.quota-enter-from { opacity: 0; transform: translateY(24px) scale(.85); }
.quota-card { transition: transform .22s ease, box-shadow .22s ease; }
.quota-card:hover { transform: translateY(-3px); box-shadow: 0 14px 32px -10px rgba(0, 0, 0, .3); }

/* ── 🌙 Mode sombre ──────────────────────────────────────────── */
.theme-dark { color-scheme: dark; }
.theme-dark aside,
.theme-dark section,
.theme-dark main { background-color: #0f0f15 !important; color: #e5e7eb; }
.theme-dark .bg-white { background-color: #1a1a22 !important; }
.theme-dark .bg-gray-50, .theme-dark .bg-gray-50\/70 { background-color: #14141c !important; }
.theme-dark .bg-gray-100, .theme-dark .bg-gray-200 { background-color: #2a2a35 !important; }
.theme-dark .border-gray-200, .theme-dark .border-gray-100, .theme-dark .border-gray-50 { border-color: #2a2a35 !important; }
.theme-dark .text-gray-900 { color: #f3f4f6 !important; }
.theme-dark .text-gray-800 { color: #e5e7eb !important; }
.theme-dark .text-gray-700 { color: #d1d5db !important; }
.theme-dark .text-gray-600, .theme-dark .text-gray-500 { color: #9ca3af !important; }
.theme-dark .text-gray-400 { color: #6b7280 !important; }
.theme-dark .text-gray-300 { color: #4b5563 !important; }
.theme-dark .hover\:bg-gray-50:hover, .theme-dark .hover\:bg-gray-100:hover { background-color: #23232e !important; }
.theme-dark .bg-\[\#FF4D2E\]\/10 { background-color: rgba(255, 77, 46, 0.18) !important; }
.theme-dark .bg-\[\#FF4D2E\]\/\[0\.06\] { background-color: rgba(255, 77, 46, 0.12) !important; }
.theme-dark input, .theme-dark textarea, .theme-dark select { background-color: #1a1a22 !important; color: #e5e7eb !important; border-color: #2a2a35 !important; }
.theme-dark [contenteditable] { background-color: #1a1a22 !important; color: #e5e7eb !important; }
.theme-dark .compose-panel { background-color: #16161e !important; }

/* Textes par défaut + tous les niveaux dans l'éditeur et le lecteur */
.theme-dark, .theme-dark * { color: #e5e7eb; }
.theme-dark .text-noliae-pulse, .theme-dark .text-\[\#FF4D2E\] { color: #FF7355 !important; }
.theme-dark .text-emerald-700, .theme-dark .text-emerald-800, .theme-dark .text-emerald-900 { color: #6ee7b7 !important; }
.theme-dark .text-rose-600, .theme-dark .text-rose-700, .theme-dark .text-rose-900 { color: #fda4af !important; }
.theme-dark .text-amber-700, .theme-dark .text-amber-800, .theme-dark .text-amber-900 { color: #fde68a !important; }
.theme-dark .text-violet-700, .theme-dark .text-violet-800 { color: #c4b5fd !important; }
.theme-dark .text-blue-700 { color: #93c5fd !important; }

/* Editeur : fond + texte */
.theme-dark [contenteditable] { background-color: #1a1a22 !important; color: #f3f4f6 !important; border-color: #2a2a35 !important; }
.theme-dark [contenteditable] * { color: inherit; }
.theme-dark [contenteditable]:empty:before { color: #6b7280 !important; }
.theme-dark [contenteditable] blockquote { border-left-color: #3a3a48 !important; color: #9ca3af !important; }

/* Toolbar éditeur */
.theme-dark .bg-gray-50, .theme-dark .bg-gray-50\/70 { background-color: #14141c !important; }
.theme-dark select option { background-color: #1a1a22 !important; color: #e5e7eb !important; }

/* Mail HTML rendu en iframe : fond clair forcé pour rester lisible
   (on ne peut pas styler l'intérieur du srcdoc, le contenu reste sur fond blanc) */
.theme-dark iframe { background: #ffffff !important; border-radius: 12px; }

/* Headers + sections noires gardent leur couleur claire (déjà OK) */
.theme-dark header { background-color: #000 !important; }

/* Toast / popovers */
.theme-dark .shadow-lg, .theme-dark .shadow-2xl, .theme-dark .shadow-xl { box-shadow: 0 10px 30px -10px rgba(0,0,0,0.6) !important; }

/* Liens dans corps mail clair */
.theme-dark a:not(.text-white):not(.text-noliae-pulse) { color: #FF7355 !important; }

/* Avatars : fond plus visible */
.theme-dark img.rounded-full { background-color: #2a2a35 !important; }

/* ── Mode confidentialité : flou général, hover révèle ─────────── */
.privacy-on aside,
.privacy-on section,
.privacy-on main {
  position: relative;
}
.privacy-on .msg-row,
.privacy-on article,
.privacy-on aside nav,
.privacy-on .compose-panel {
  filter: blur(7px);
  transition: filter .25s ease;
  user-select: none;
}
.privacy-on .msg-row:hover,
.privacy-on article:hover,
.privacy-on aside nav:hover,
.privacy-on .compose-panel:hover {
  filter: blur(0);
}
.privacy-on iframe { filter: blur(7px); transition: filter .25s ease; }
.privacy-on main:hover iframe { filter: blur(0); }
/* Le header (logo + photo + bouton œil) reste lisible pour pouvoir désactiver */

/* Chips destinataires */
.chip {
  display: inline-flex; align-items: center; gap: .375rem;
  padding: .15rem .25rem .15rem .625rem;
  border-radius: 9999px; font-size: 12px; font-weight: 600;
  transition: transform .12s ease;
}
.chip:hover { transform: translateY(-1px); }
.chip-ok { background: #FFE5DF; color: #B6371D; }
.chip-bad { background: #fee2e2; color: #b91c1c; border: 1px dashed #fca5a5; }
.chip-x {
  width: 18px; height: 18px; border-radius: 9999px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 14px; line-height: 1; color: inherit; opacity: .55;
}
.chip-x:hover { opacity: 1; background: rgba(0, 0, 0, .08); }

/* Apparition du message ouvert */
.reader-in { animation: readerIn .38s cubic-bezier(.16, 1, .3, 1); }
@keyframes readerIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: none; }
}

/* ── Composeur : ouverture / fermeture ── */
.modal-enter-active, .modal-leave-active { transition: opacity .25s ease; }
.modal-enter-active .compose-panel,
.modal-leave-active .compose-panel { transition: transform .28s cubic-bezier(.16, 1, .3, 1), opacity .28s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .compose-panel,
.modal-leave-to .compose-panel { transform: scale(.94) translateY(22px); opacity: 0; }

/* Panneaux qui se déplient (IA, stockage) */
.panel-down-enter-active, .panel-down-leave-active {
  transition: opacity .25s ease, transform .25s cubic-bezier(.16, 1, .3, 1), max-height .3s ease;
  overflow: hidden;
}
.panel-down-enter-from, .panel-down-leave-to {
  opacity: 0; transform: translateY(-8px); max-height: 0;
}
.panel-down-enter-to, .panel-down-leave-from {
  opacity: 1; transform: none; max-height: 320px;
}

/* Popovers (émojis, menus) */
.pop-enter-active, .pop-leave-active { transition: all .18s cubic-bezier(.16, 1, .3, 1); transform-origin: bottom left; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: scale(.85) translateY(6px); }

/* Voile mobile */
.fade-enter-active, .fade-leave-active { transition: opacity .22s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Chips de pièces jointes (TransitionGroup) */
.chip-in-enter-active, .chip-in-leave-active { transition: all .22s cubic-bezier(.16, 1, .3, 1); }
.chip-in-enter-from { opacity: 0; transform: translateY(-6px) scale(.85); }
.chip-in-leave-to   { opacity: 0; transform: scale(.7); }
.chip-in-move       { transition: transform .25s ease; }

/* Apparition des mails dans la liste (insertion) */
.msg-row { animation: msgIn .28s cubic-bezier(.16, 1, .3, 1) both; }
@keyframes msgIn {
  from { opacity: 0; transform: translateX(-8px); }
  to   { opacity: 1; transform: none; }
}

/* Boutons : retour visuel au clic */
button { transition: transform .14s ease, background-color .15s ease, color .15s ease; }
button:active:not(:disabled) { transform: scale(.94); }
</style>
