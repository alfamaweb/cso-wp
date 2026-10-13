/**
 * Alfama WEB v3 — JS do tema.
 *
 * DÍVIDAS RESOLVIDAS (v2): sem jQuery (era a 1.11.1, de 2014, carregada à mão
 * no header), sem Bootstrap JS e sem <script> inline em wp_footer. Tudo aqui é
 * ES2018+ com fetch e AbortController, progressivamente aprimorado: menu,
 * formulário e filtro funcionam sem JS, e só ficam melhores com ele.
 *
 * Depende de window.AW, injetado por wp_localize_script (inc/assets.php).
 */
(function () {
	'use strict';

	var AW = window.AW || {};
	var reduzirMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* =====================================================================
	 * Utilidades
	 * ================================================================== */

	function $(seletor, escopo) {
		return (escopo || document).querySelector(seletor);
	}

	function $$(seletor, escopo) {
		return Array.prototype.slice.call((escopo || document).querySelectorAll(seletor));
	}

	/**
	 * Escapa texto vindo do banco antes de entrar em innerHTML.
	 *
	 * @param {string} texto
	 * @returns {string}
	 */
	function esc(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	function debounce(fn, espera) {
		var timer;
		return function () {
			var args = arguments;
			var ctx = this;
			clearTimeout(timer);
			timer = setTimeout(function () {
				fn.apply(ctx, args);
			}, espera);
		};
	}

	/**
	 * POST para admin-ajax.php com o nonce do tema.
	 *
	 * @param {string} acao
	 * @param {Object|FormData} dados
	 * @param {AbortSignal} [signal]
	 * @returns {Promise<Object>}
	 */
	function ajax(acao, dados, signal) {
		var body = dados instanceof FormData ? dados : new FormData();

		if (!(dados instanceof FormData)) {
			Object.keys(dados || {}).forEach(function (chave) {
				var valor = dados[chave];

				if (Array.isArray(valor)) {
					valor.forEach(function (v) {
						body.append(chave + '[]', v);
					});
				} else if (valor !== null && valor !== undefined && valor !== '') {
					body.append(chave, valor);
				}
			});
		}

		body.set('action', acao);
		body.set('nonce', AW.nonce || '');

		return fetch(AW.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
			signal: signal
		}).then(function (r) {
			return r.json();
		});
	}

	/* =====================================================================
	 * Menu mobile
	 * ================================================================== */

	function iniciarMenu() {
		var toggle = $('[data-aw-menu-toggle]');
		var menu = $('#menu-mobile');

		if (!toggle || !menu) {
			return;
		}

		var fechar = $('[data-aw-menu-close]', menu);

		function abrir() {
			menu.hidden = false;
			toggle.setAttribute('aria-expanded', 'true');
			document.body.classList.add('aw-menu-aberto');

			var primeiroLink = $('a, button', menu);
			if (primeiroLink) {
				primeiroLink.focus();
			}
		}

		function esconder() {
			menu.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
			document.body.classList.remove('aw-menu-aberto');
			toggle.focus();
		}

		toggle.addEventListener('click', abrir);

		if (fechar) {
			fechar.addEventListener('click', esconder);
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !menu.hidden) {
				esconder();
			}
		});

		// Fecha ao navegar para uma âncora da própria página.
		$$('a[href^="#"]', menu).forEach(function (link) {
			link.addEventListener('click', esconder);
		});
	}

	/* =====================================================================
	 * Swiper — inicializado por data-attribute, sem JS por página
	 * ================================================================== */

	function iniciarSwipers() {
		if (typeof window.Swiper !== 'function') {
			return;
		}

		$$('[data-aw-swiper]').forEach(function (el) {
			// Guarda de idempotência: esta função roda no DOMContentLoaded e
			// de novo no load, porque o Swiper vem do rodapé.
			if (el.dataset.awSwiperPronto === '1') {
				return;
			}

			el.dataset.awSwiperPronto = '1';

			var opcoes = { slidesPerView: 1, spaceBetween: 24 };

			try {
				opcoes = Object.assign(opcoes, JSON.parse(el.dataset.awSwiper || '{}'));
			} catch (e) {
				// data-attribute malformado: segue com o padrão.
			}

			if (reduzirMovimento) {
				opcoes.autoplay = false;
				opcoes.speed = 0;
			}

			// data-aw-swiper-min="768": só vira carrossel a partir dessa largura.
			// Abaixo dela os slides ficam empilhados (o CSS cuida) e quem pagina é
			// iniciarPaginasMobile(). Cruzando o limite, cria ou destrói o Swiper.
			var minimo = parseInt(el.dataset.awSwiperMin || '0', 10);

			if (!minimo) {
				new window.Swiper(el, opcoes);
				return;
			}

			var consulta = window.matchMedia('(min-width: ' + minimo + 'px)');
			var instancia = null;

			function aplicar() {
				if (consulta.matches && !instancia) {
					instancia = new window.Swiper(el, opcoes);
				} else if (!consulta.matches && instancia) {
					instancia.destroy(true, true);
					instancia = null;
				}
			}

			aplicar();
			consulta.addEventListener('change', aplicar);
		});
	}

	/* =====================================================================
	 * Fancybox
	 * ================================================================== */

	var fancyboxPronto = false;

	function iniciarFancybox() {
		if (fancyboxPronto) {
			return;
		}

		if (window.Fancybox && typeof window.Fancybox.bind === 'function') {
			window.Fancybox.bind('[data-fancybox]', {});
			fancyboxPronto = true;
		}
	}

	/* =====================================================================
	 * Filtro e paginação assíncronos
	 * ================================================================== */

	function iniciarFiltros() {
		$$('[data-aw-filtro]').forEach(function (form) {
			var grid = $(form.dataset.target);
			var wrapPaginacao = form.dataset.pagination ? $(form.dataset.pagination) : null;
			var contador = form.dataset.count ? $(form.dataset.count) : null;
			var acao = form.dataset.action;

			if (!grid || !acao) {
				return;
			}

			var controller = null;
			var paginaAtual = 1;

			function skeletons() {
				var quantos = Math.max(3, grid.children.length || 6);
				var html = '';

				for (var i = 0; i < quantos; i++) {
					html += '<div class="aw-skeleton" aria-hidden="true"></div>';
				}

				grid.setAttribute('aria-busy', 'true');
				grid.innerHTML = html;
			}

			function buscar(pagina) {
				paginaAtual = pagina || 1;

				// Cancela a requisição anterior: respostas lentas não podem
				// sobrescrever uma busca mais recente.
				if (controller) {
					controller.abort();
				}

				controller = new AbortController();

				var dados = new FormData(form);
				dados.delete('action');
				dados.set('paged', String(paginaAtual));

				skeletons();

				var inicio = Date.now();

				ajax(acao, dados, controller.signal)
					.then(function (resposta) {
						if (!resposta || !resposta.success) {
							throw new Error((resposta && resposta.data && resposta.data.mensagem) || AW.i18n.erroGenerico);
						}

						// Espera mínima de 350 ms para o skeleton não piscar.
						var espera = Math.max(0, 350 - (Date.now() - inicio));

						setTimeout(function () {
							aplicar(resposta.data);
						}, espera);
					})
					.catch(function (erro) {
						if (erro.name === 'AbortError') {
							return;
						}

						grid.setAttribute('aria-busy', 'false');
						grid.innerHTML = '<p class="aw-vazio">' + (erro.message || AW.i18n.erroGenerico) + '</p>';
					});
			}

			function aplicar(dados) {
				grid.setAttribute('aria-busy', 'false');
				grid.innerHTML = dados.cards || '';

				if (wrapPaginacao) {
					wrapPaginacao.innerHTML = dados.paginacao || '';
				}

				if (contador) {
					if (dados.fallback) {
						contador.textContent = AW.i18n.semResultado + ' ' +
							(dados.termoBusca ? '“' + dados.termoBusca + '”' : '');
					} else {
						contador.textContent = dados.total + '';
					}
				}
			}

			// Busca textual com debounce; selects reagem na hora.
			$$('input[type="search"], input[type="text"]', form).forEach(function (input) {
				input.addEventListener('input', debounce(function () {
					buscar(1);
				}, 400));
			});

			$$('select, input[type="checkbox"], input[type="radio"]', form).forEach(function (campo) {
				campo.addEventListener('change', function () {
					buscar(1);
				});
			});

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				buscar(1);
			});

			form.addEventListener('reset', function () {
				setTimeout(function () {
					buscar(1);
				}, 0);
			});

			// Paginação: delegação, porque o HTML é substituído a cada busca.
			if (wrapPaginacao) {
				wrapPaginacao.addEventListener('click', function (e) {
					var botao = e.target.closest('[data-page]');

					if (!botao || botao.disabled) {
						return;
					}

					buscar(parseInt(botao.dataset.page, 10) || 1);
					grid.scrollIntoView({ behavior: reduzirMovimento ? 'auto' : 'smooth', block: 'start' });
				});
			}

			// Limpa da barra de endereços os parâmetros já aplicados no SSR.
			if (window.location.search.indexOf('termo=') > -1 || window.location.search.indexOf('cidade=') > -1) {
				window.history.replaceState({}, document.title, window.location.pathname);
			}
		});
	}

	/* =====================================================================
	 * Cascata cidade → bairro
	 * ================================================================== */

	function iniciarCascata() {
		$$('[data-aw-cascata]').forEach(function (select) {
			var alvo = $(select.dataset.awCascataAlvo);

			if (!alvo) {
				return;
			}

			var vazio = alvo.querySelector('option') ? alvo.querySelector('option').textContent : '';

			select.addEventListener('change', function () {
				alvo.disabled = true;
				alvo.innerHTML = '<option value="">…</option>';

				if (!select.value) {
					alvo.innerHTML = '<option value="">' + esc(vazio) + '</option>';
					return;
				}

				ajax('aw_termos_filhos', {
					taxonomia: select.dataset.awCascata,
					pai: select.value
				}).then(function (resposta) {
					var termos = (resposta && resposta.success && resposta.data.termos) || [];

					if (!termos.length) {
						alvo.innerHTML = '<option value="">' + esc(vazio) + '</option>';
						return;
					}

					var html = '<option value="">' + esc(vazio) + '</option>';

					termos.forEach(function (termo) {
						html += '<option value="' + esc(termo.slug) + '">' + esc(termo.nome) + '</option>';
					});

					alvo.innerHTML = html;
					alvo.disabled = false;
				}).catch(function () {
					alvo.innerHTML = '<option value="">' + esc(vazio) + '</option>';
				});
			});
		});
	}

	/* =====================================================================
	 * Formulários
	 * ================================================================== */

	/**
	 * Resolve o token do reCAPTCHA v3, se estiver ativo.
	 *
	 * @param {string} acao
	 * @returns {Promise<string>}
	 */
	function tokenRecaptcha(acao) {
		if (!window.grecaptcha || !window.AW_RECAPTCHA_KEY) {
			return Promise.resolve('');
		}

		return new Promise(function (resolve) {
			window.grecaptcha.ready(function () {
				window.grecaptcha
					.execute(window.AW_RECAPTCHA_KEY, { action: acao })
					.then(resolve)
					.catch(function () {
						resolve('');
					});
			});
		});
	}

	function iniciarFormularios() {
		$$('form[data-aw-form-ajax]').forEach(function (form) {
			var botao = $('[data-aw-form-submit]', form);
			var spinner = botao ? $('.aw-spinner', botao) : null;
			var status = $('.aw-form-status', form);

			function mostrar(tipo, texto) {
				if (!status) {
					return;
				}

				status.hidden = false;
				status.className = 'aw-form-status is-' + tipo;
				status.textContent = texto;
			}

			form.addEventListener('submit', function (e) {
				e.preventDefault();

				if (botao) {
					botao.disabled = true;
				}

				if (spinner) {
					spinner.hidden = false;
				}

				var campoToken = form.querySelector('[name="aw_recaptcha_token"]');
				var acaoRecaptcha = campoToken ? campoToken.dataset.awRecaptchaAction || 'form' : 'form';

				tokenRecaptcha(acaoRecaptcha)
					.then(function (token) {
						if (campoToken) {
							campoToken.value = token;
						}

						var dados = new FormData(form);
						dados.delete('action');

						return ajax('aw_form', dados);
					})
					.then(function (resposta) {
						var payload = (resposta && resposta.data) || {};

						if (resposta && resposta.success) {
							mostrar('success', payload.mensagem);
							form.reset();
						} else {
							mostrar('error', payload.mensagem || AW.i18n.erroGenerico);
						}
					})
					.catch(function () {
						mostrar('error', AW.i18n.erroGenerico);
					})
					.then(function () {
						if (botao) {
							botao.disabled = false;
						}

						if (spinner) {
							spinner.hidden = true;
						}
					});
			});
		});
	}

	/* =====================================================================
	 * Máscara de telefone (BR), sem dependência externa
	 * ================================================================== */

	function iniciarMascaras() {
		$$('[data-aw-mask="telefone"]').forEach(function (input) {
			input.addEventListener('input', function () {
				var d = input.value.replace(/\D/g, '').slice(0, 11);

				if (d.length <= 10) {
					input.value = d
						.replace(/^(\d{2})(\d)/, '($1) $2')
						.replace(/(\d{4})(\d)/, '$1-$2');
				} else {
					input.value = d
						.replace(/^(\d{2})(\d)/, '($1) $2')
						.replace(/(\d{5})(\d)/, '$1-$2');
				}
			});
		});
	}

	/* =====================================================================
	 * Toast do fluxo sem JS: limpa a query string e some sozinho
	 * ================================================================== */

	function iniciarToast() {
		var toast = $('[data-aw-toast]');

		if (!toast) {
			return;
		}

		toast.focus();

		var url = new URL(window.location.href);
		url.searchParams.delete('aw_form');
		window.history.replaceState({}, document.title, url.pathname + (url.search || '') + url.hash);

		var fechar = $('[data-aw-toast-fechar]', toast);

		function esconder() {
			toast.remove();
		}

		if (fechar) {
			fechar.addEventListener('click', esconder);
		}

		if (!toast.classList.contains('aw-toast--error')) {
			setTimeout(esconder, 8000);
		}
	}

	/* =====================================================================
	 * Abas de filtro no cliente (home: empreendimentos por status)
	 *
	 * <section data-aw-abas>
	 *   <button data-aw-aba="slug|todos">…</button>
	 *   <div data-aw-abas-alvo data-limite="3"> <article data-status="slug"> … </div>
	 * ================================================================== */

	function iniciarAbas() {
		$$('[data-aw-abas]').forEach(function (secao) {
			var abas = $$('[data-aw-aba]', secao);
			var alvo = $('[data-aw-abas-alvo]', secao);

			if (!abas.length || !alvo) {
				return;
			}

			var limite = parseInt(alvo.dataset.limite || '0', 10) || Infinity;
			var itens = $$('[data-status]', alvo);
			var vazio = $('[data-aw-abas-vazio]', alvo);

			function filtrar(slug) {
				var visiveis = 0;

				itens.forEach(function (item) {
					var combina = slug === 'todos' || item.dataset.status === slug;
					var mostrar = combina && visiveis < limite;

					item.hidden = !mostrar;

					if (mostrar) {
						visiveis++;
					}
				});

				if (vazio) {
					vazio.hidden = visiveis > 0;
				}
			}

			abas.forEach(function (aba) {
				aba.addEventListener('click', function () {
					abas.forEach(function (outra) {
						var ativa = outra === aba;
						outra.classList.toggle('is-ativa', ativa);
						outra.setAttribute('aria-selected', ativa ? 'true' : 'false');
					});

					filtrar(aba.dataset.awAba);
				});
			});
		});
	}

	/* =====================================================================
	 * Plantas do empreendimento: lista de tipologias troca a imagem
	 * ([data-aw-plantas] em single-empreendimento.php)
	 * ================================================================== */

	function iniciarPlantas() {
		$$('[data-aw-plantas]').forEach(function (secao) {
			var abas = $$('[data-aw-planta]', secao);
			var paineis = $$('[role="tabpanel"]', secao);
			var atual = 0;

			function mostrar(i, focar) {
				atual = (i + abas.length) % abas.length;

				abas.forEach(function (aba, j) {
					var ativa = j === atual;
					aba.classList.toggle('is-ativa', ativa);
					aba.setAttribute('aria-selected', ativa ? 'true' : 'false');
					aba.tabIndex = ativa ? 0 : -1;
				});

				paineis.forEach(function (painel, j) {
					painel.hidden = j !== atual;
				});

				if (focar) {
					abas[atual].focus();
				}
			}

			abas.forEach(function (aba, i) {
				aba.addEventListener('click', function () {
					mostrar(i);
				});

				// Setas do teclado entre as abas (padrão WAI-ARIA de tabs).
				aba.addEventListener('keydown', function (e) {
					if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
						e.preventDefault();
						mostrar(atual + 1, true);
					} else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
						e.preventDefault();
						mostrar(atual - 1, true);
					}
				});
			});

			$$('[data-aw-planta-passo]', secao).forEach(function (botao) {
				botao.addEventListener('click', function () {
					mostrar(atual + parseInt(botao.dataset.awPlantaPasso, 10));
				});
			});

			if (abas.length) {
				mostrar(0);
			}
		});
	}

	/* =====================================================================
	 * Boot
	 * ================================================================== */

	/* ==================================================================
	 * Vídeo de fundo do hero da home
	 *
	 * Muted, loop, sem controles. Só baixa depois do load (a capa é o LCP)
	 * e escolhe o arquivo mobile ou desktop pela largura. Não baixa com
	 * "reduzir movimento", economia de dados ou conexão 2G — fica a capa.
	 * Pausa fora da tela e com a aba oculta.
	 * ================================================================== */

	function iniciarVideoHero() {
		var video = document.querySelector('[data-aw-hero-video]');

		if (!video || video.dataset.awIniciado) {
			return;
		}

		video.dataset.awIniciado = '1';

		var conexao = navigator.connection || {};
		var reduzir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var economia = conexao.saveData || /(^|-)2g$/.test(conexao.effectiveType || '');

		if (reduzir || economia) {
			return;
		}

		var mobile = window.matchMedia('(max-width: 767.98px)').matches;
		var src = mobile ? video.dataset.srcMobile : video.dataset.srcDesktop;
		var naTela = true;

		if (!src) {
			return;
		}

		function tocar() {
			if (!naTela || document.hidden) {
				return;
			}

			var promessa = video.play();

			if (promessa && promessa.catch) {
				promessa.catch(function () {}); // autoplay bloqueado: fica a capa
			}
		}

		function carregar() {
			video.muted = true; // o iOS exige a propriedade, não só o atributo
			video.addEventListener('playing', function () {
				video.classList.add('is-tocando');
			}, { once: true });
			video.src = src;
			tocar();

			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (entradas) {
					naTela = entradas[0].isIntersecting;
					naTela ? tocar() : video.pause();
				}).observe(video);
			}

			document.addEventListener('visibilitychange', function () {
				document.hidden ? video.pause() : tocar();
			});
		}

		function agendar() {
			if ('requestIdleCallback' in window) {
				window.requestIdleCallback(carregar, { timeout: 1500 });
			} else {
				window.setTimeout(carregar, 200);
			}
		}

		if (document.readyState === 'complete') {
			agendar();
		} else {
			window.addEventListener('load', agendar, { once: true });
		}
	}

	/* ==================================================================
	 * Bolinhas de carrossel por rolagem (mobile)
	 *
	 * [data-aw-pontos="#trilho"]: monta uma bolinha por item visível do
	 * trilho (rolagem horizontal com scroll-snap, ver home.css) e marca a
	 * do item na tela. No desktop o trilho é uma grade e as bolinhas ficam
	 * escondidas pelo CSS (.pontos--mobile).
	 * ================================================================== */

	function iniciarPontosRolagem() {
		$$('[data-aw-pontos]').forEach(function (pontos) {
			if (pontos.dataset.awPronto) {
				return;
			}

			var trilho = document.querySelector(pontos.dataset.awPontos);

			if (!trilho) {
				return;
			}

			pontos.dataset.awPronto = '1';

			var itens = Array.prototype.filter.call(trilho.children, function (item) {
				return !item.hidden;
			});

			if (itens.length < 2) {
				pontos.hidden = true;
				return;
			}

			var botoes = itens.map(function (item, i) {
				var botao = document.createElement('button');
				botao.type = 'button';
				botao.className = 'pontos__item';
				botao.setAttribute('aria-label', 'Ir para o item ' + (i + 1) + ' de ' + itens.length);
				botao.addEventListener('click', function () {
					trilho.scrollTo({
						left: item.offsetLeft - itens[0].offsetLeft,
						behavior: reduzirMovimento ? 'auto' : 'smooth',
					});
				});
				pontos.appendChild(botao);
				return botao;
			});

			var agendado = false;

			function marcar() {
				agendado = false;
				var passo = (itens[1].offsetLeft - itens[0].offsetLeft) || trilho.clientWidth || 1;
				var atual = Math.min(itens.length - 1, Math.max(0, Math.round(trilho.scrollLeft / passo)));

				botoes.forEach(function (botao, i) {
					botao.classList.toggle('is-ativo', i === atual);
					botao.setAttribute('aria-current', i === atual ? 'true' : 'false');
				});
			}

			trilho.addEventListener('scroll', function () {
				if (!agendado) {
					agendado = true;
					window.requestAnimationFrame(marcar);
				}
			}, { passive: true });

			marcar();
		});
	}

	/* ==================================================================
	 * Páginas no mobile (lista empilhada com setas)
	 *
	 * [data-aw-paginas="2"] (com id) mostra N itens por vez até 767px; os
	 * botões [data-aw-paginas-anterior] / [data-aw-paginas-proxima] dentro de
	 * [data-aw-paginas-de="#id"] trocam a página. Usado na história (Quem
	 * Somos) e nos diferenciais do empreendimento, que no desktop são
	 * carrosséis Swiper (data-aw-swiper-min="768").
	 * ================================================================== */

	function iniciarPaginasMobile() {
		var consulta = window.matchMedia('(max-width: 767.98px)');

		$$('[data-aw-paginas]').forEach(function (lista) {
			if (lista.dataset.awPaginasPronto) {
				return;
			}

			lista.dataset.awPaginasPronto = '1';

			var porPagina = parseInt(lista.dataset.awPaginas, 10) || 1;
			var trilho = lista.querySelector('.swiper-wrapper') || lista;
			var itens = Array.prototype.slice.call(trilho.children);
			var controles = lista.id ? document.querySelector('[data-aw-paginas-de="#' + lista.id + '"]') : null;
			var anterior = controles && controles.querySelector('[data-aw-paginas-anterior]');
			var proxima = controles && controles.querySelector('[data-aw-paginas-proxima]');
			var total = Math.ceil(itens.length / porPagina);
			var pagina = 0;

			function mostrar() {
				var ativo = consulta.matches;

				itens.forEach(function (item, i) {
					item.hidden = ativo && Math.floor(i / porPagina) !== pagina;
				});

				if (controles) {
					controles.hidden = !ativo || total < 2;
				}

				if (anterior) {
					anterior.disabled = pagina === 0;
				}

				if (proxima) {
					proxima.disabled = pagina >= total - 1;
				}
			}

			function ir(passo) {
				pagina = Math.min(total - 1, Math.max(0, pagina + passo));
				mostrar();
			}

			if (anterior) {
				anterior.addEventListener('click', function () { ir(-1); });
			}

			if (proxima) {
				proxima.addEventListener('click', function () { ir(1); });
			}

			consulta.addEventListener('change', mostrar);
			mostrar();
		});
	}

	function iniciar() {
		iniciarVideoHero();
		iniciarPontosRolagem();
		iniciarPaginasMobile();
		iniciarMenu();
		iniciarSwipers();
		iniciarFancybox();
		// A cascata é registrada ANTES do filtro de propósito: ao trocar a
		// cidade, o handler dela zera o select de bairro, e só então o handler
		// do filtro monta o FormData — sem isso, a primeira busca sairia com o
		// bairro da cidade anterior.
		iniciarCascata();
		iniciarFiltros();
		iniciarFormularios();
		iniciarMascaras();
		iniciarToast();
		iniciarAbas();
		iniciarPlantas();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', iniciar);
	} else {
		iniciar();
	}

	// Swiper e Fancybox são carregados no rodapé e podem chegar depois do
	// DOMContentLoaded; o load garante a inicialização em qualquer ordem.
	window.addEventListener('load', function () {
		iniciarSwipers();
		iniciarFancybox();
	});
})();
