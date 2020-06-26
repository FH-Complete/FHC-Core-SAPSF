CREATE TABLE IF NOT EXISTS sync.tbl_sap_stundensatz (
	mitarbeiter_uid varchar(32) NOT NULL,
	sap_kalkulatorischer_stundensatz numeric(6,2),
	insertamum timestamp DEFAULT now()
);

COMMENT ON TABLE sync.tbl_sap_stundensatz IS 'Synchronization table for kalkulatorischer Stundensatz between SAP Success Factors and SAP ByD';
COMMENT ON COLUMN sync.tbl_sap_stundensatz.mitarbeiter_uid IS 'Mitarbeiteruid';
COMMENT ON COLUMN sync.tbl_sap_stundensatz.sap_kalkulatorischer_stundensatz IS 'Stundensatz from SAP Success Factors';

DO $$
BEGIN
	ALTER TABLE ONLY sync.tbl_sap_stundensatz ADD CONSTRAINT fk_tbl_sap_stundensatz_mitarbeiter_uid
	FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_stundensatz TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_stundensatz TO web;