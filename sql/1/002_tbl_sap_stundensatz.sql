CREATE SEQUENCE IF NOT EXISTS sync.tbl_sap_stundensatz_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON SEQUENCE sync.tbl_sap_stundensatz_id_seq TO vilesci;

CREATE TABLE IF NOT EXISTS sync.tbl_sap_stundensatz (
    sap_stundensatz_id bigint NOT NULL DEFAULT NEXTVAL('sync.tbl_sap_stundensatz_id_seq'::regclass),
	mitarbeiter_uid varchar(32) NOT NULL,
	sap_kalkulatorischer_stundensatz numeric(6,2),
	insertamum timestamp DEFAULT now()
);

COMMENT ON TABLE sync.tbl_sap_stundensatz IS 'Synchronization table for kalkulatorischer Stundensatz between SAP Success Factors and SAP ByD';
COMMENT ON COLUMN sync.tbl_sap_stundensatz.mitarbeiter_uid IS 'Mitarbeiteruid';
COMMENT ON COLUMN sync.tbl_sap_stundensatz.sap_kalkulatorischer_stundensatz IS 'Stundensatz from SAP Success Factors';

DO $$
BEGIN
    ALTER TABLE sync.tbl_sap_stundensatz ADD CONSTRAINT tbl_sap_stundensatz_pkey PRIMARY KEY (sap_stundensatz_id);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE ONLY sync.tbl_sap_stundensatz ADD CONSTRAINT fk_tbl_sap_stundensatz_mitarbeiter_uid
	FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        CREATE INDEX idx_tbl_sap_stundensatz_mitarbeiter_uid ON sync.tbl_sap_stundensatz USING btree (mitarbeiter_uid);
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_stundensatz TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_stundensatz TO web;