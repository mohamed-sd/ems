-- NAV-09: أكوادُ المجموعات المولَّدة (n9sDD_S_G_rRR) أطولُ من عرض العمود القديم (10)
-- فكانت تُبتر «n9s99_othe» — يتسع لأربعين حرفًا.
ALTER TABLE link_groups MODIFY COLUMN group_code VARCHAR(40) NULL;
