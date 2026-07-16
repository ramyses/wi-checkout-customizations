# HISTORIC

1. **003-checkout-mobile-responsiveness** [planned] — Corrige responsividade do checkout no mobile: desalinhamento Número/Complemento, sobreposição do balão do WhatsApp sobre o formulário, e reavaliação segura do fix de padding do #main-content.
2. **002-checkout-antifraude-mercadopago** [finished] — Alinha o checkout às exigências antifraude do Mercado Pago: protege o script de Device ID no LiteSpeed, corrige o campo de Nome da Empresa escondido no mobile para Pessoa Jurídica, e confirma validação/obrigatoriedade dos campos PF/PJ.
3. **001-checkout-hardening** [finished] — Hardening de robustez, segurança e performance do checkout: guardas contra falhas de filesystem, remoção de escrita em banco disparada por visitante anônimo, checagem de Elementor Pro. Tentativa de eliminar o CLS via CSS (Step 2) foi revertida após verificação local mostrar que quebrava o widget do Mercado Pago; reorder continua via JS.
