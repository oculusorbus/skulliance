<?php /* The admin panel's styling, shared by the three pages. Scoped under
         .adm so nothing here can reach the rest of the platform.

         .adm is a flex child of header.php's .row at full width, because
         the only column classes in dist/flexbox.css are .col1of2 and
         .col1of3 (flex: 33%) and neither suits a wide table. */ ?>
<style>
.adm{flex:1 1 100%;min-width:0;padding:20px;color:#c8d8e8;
     font:400 15px/1.6 Arial,Helvetica,sans-serif}
.adm h2{color:#00c8a0;letter-spacing:.04em;text-transform:uppercase;margin:0;font-size:1.3rem}
.adm h3{font-size:1.05rem;letter-spacing:.03em;text-transform:uppercase;color:#c8d8e8;margin:0 0 4px}
.adm h4{font-size:.9rem;letter-spacing:.03em;text-transform:uppercase;color:#5a7888;margin:18px 0 0}
.adm-head{display:flex;flex-wrap:wrap;gap:14px;align-items:baseline;justify-content:space-between;
          padding:0 0 14px;border-bottom:1px solid rgba(0,200,160,.14);margin-bottom:18px}
.adm-tabs{display:flex;gap:6px;flex-wrap:wrap}
.adm-tabs a{padding:9px 14px;border:1px solid rgba(0,200,160,.14);color:#5a7888;
            text-decoration:none;font-size:.82rem}
.adm-tabs a.on{background:#00c8a0;color:#07111d;border-color:#00c8a0;font-weight:bold}
.adm-msg{margin:12px 0;padding:11px 14px;border-left:3px solid;font-size:.88rem}
.adm-msg.bad{border-color:#e0705f;background:rgba(224,112,95,.1);color:#e0705f}
.adm-msg.good{border-color:#00c8a0;background:rgba(0,200,160,.1);color:#00c8a0}
.adm-pick{display:flex;gap:10px;align-items:center;margin:0 0 18px;flex-wrap:wrap}
.adm-pick label{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;color:#5a7888}
/* INSURANCE, AND THE REASON THE PANEL WAS INVISIBLE. dist/flexbox.css
   carries a BARE ELEMENT rule -- `section { opacity: 0 }` with a
   `section.active { opacity: 1 }` revealed by a scroll observer in
   skulliance.js. The admin cards were <section> and these pages do not
   load that script, so every card rendered at full size with nothing
   painted in it: present in the DOM, correctly laid out, completely
   invisible. The cards are <div> now; this keeps anything nested under
   .adm visible even if a <section> comes back. */
.adm section{opacity:1}
.adm-card{background:#0a1929;border:1px solid rgba(0,200,160,.14);padding:20px;margin:0 0 18px}
.adm-form{display:flex;flex-direction:column;gap:14px;margin-top:12px}
.adm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
.adm label{display:flex;flex-direction:column;gap:5px;font-size:.74rem;letter-spacing:.1em;
           text-transform:uppercase;color:#5a7888;min-width:0}
.adm-wide{width:100%}
.adm input,.adm select,.adm textarea{background:#07111d;border:1px solid rgba(0,200,160,.18);
  color:#c8d8e8;padding:9px 10px;font:400 14px/1.4 Arial,sans-serif;text-transform:none;
  letter-spacing:0;border-radius:0;max-width:100%}
.adm input:focus,.adm select:focus,.adm textarea:focus{outline:2px solid #00c8a0;outline-offset:1px}
.adm small{font-size:.72rem;letter-spacing:0;text-transform:none;color:#5a7888;line-height:1.45}
.adm button{background:#00c8a0;color:#07111d;border:0;padding:11px 20px;font-weight:bold;
            cursor:pointer;align-self:flex-start;font-size:.85rem}
.adm button:hover{background:#00e0b4}
.adm-note{font-size:.84rem;color:#5a7888;margin:10px 0 0}
.adm-note .warn{color:#e8b14c}
.adm-icon{width:26px;height:26px;vertical-align:middle;margin-left:6px}
.adm-tablewrap{overflow-x:auto;margin-top:12px}
.adm-table{border-collapse:collapse;width:100%;min-width:620px;font-size:.86rem}
.adm-table th{text-align:left;padding:9px 10px;border-bottom:1px solid rgba(0,200,160,.14);
  color:#5a7888;font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;font-weight:normal}
.adm-table td{padding:8px 10px;border-bottom:1px solid rgba(0,200,160,.06)}
.adm-table .mono{font-family:ui-monospace,Menlo,monospace;font-variant-numeric:tabular-nums}
.adm-table .trunc{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.adm-table a{color:#00c8a0}
.ok-dot{color:#00c8a0}.bad-dot{color:#e0705f}
@media (max-width:560px){.adm-head{flex-direction:column;align-items:flex-start}}
</style>
